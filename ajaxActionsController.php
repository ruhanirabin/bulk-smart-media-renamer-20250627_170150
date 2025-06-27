<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BSMR_AJAXActionsController {

    private static $instance = null;
    private $filesystem_initialized = false;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_bsmr_fetch_attachments', [ $this, 'fetch_attachments' ] );
        add_action( 'wp_ajax_bsmr_generate_preview',   [ $this, 'generate_preview' ] );
        add_action( 'wp_ajax_bsmr_perform_rename',    [ $this, 'perform_rename' ] );
    }

    private function verify_request() {
        if ( empty( $_POST['nonce'] ) ) {
            wp_send_json_error( 'Missing nonce', 400 );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'bsmr_nonce_action' ) ) {
            wp_send_json_error( 'Invalid nonce', 403 );
        }
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Insufficient permissions', 403 );
        }
    }

    public function fetch_attachments() {
        $this->verify_request();

        $page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 50;

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query       = new WP_Query( $args );
        $attachments = $query->posts;
        $result      = [];

        foreach ( $attachments as $att ) {
            $file = get_attached_file( $att->ID );
            $result[] = [
                'id'       => $att->ID,
                'title'    => get_the_title( $att->ID ),
                'filename' => $file ? wp_basename( $file ) : '',
                'url'      => wp_get_attachment_url( $att->ID ),
            ];
        }

        $total_posts = intval( $query->found_posts );
        $total_pages = intval( $query->max_num_pages );

        wp_send_json_success( [
            'attachments' => $result,
            'pagination'  => [
                'current_page' => $page,
                'per_page'     => $per_page,
                'total_posts'  => $total_posts,
                'total_pages'  => $total_pages,
            ],
        ] );
    }

    public function generate_preview() {
        $this->verify_request();

        if ( empty( $_POST['attachment_ids'] ) || ! is_array( $_POST['attachment_ids'] ) ) {
            wp_send_json_error( 'No attachments selected', 400 );
        }
        $template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
        $ids      = array_map( 'intval', wp_unslash( $_POST['attachment_ids'] ) );
        $payload  = [];

        foreach ( $ids as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file ) {
                continue;
            }
            $payload[] = [
                'id'       => $id,
                'filename' => wp_basename( $file ),
                'template' => $template,
            ];
        }

        try {
            $preview = $this->call_ai_service( $payload );
            wp_send_json_success( $preview );
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage(), 500 );
        }
    }

    public function perform_rename() {
        $this->verify_request();

        if ( empty( $_POST['renames'] ) || ! is_array( $_POST['renames'] ) ) {
            wp_send_json_error( 'No rename data provided', 400 );
        }

        $this->init_filesystem();
        global $wp_filesystem;

        $renames = wp_unslash( $_POST['renames'] );
        $results = [];

        foreach ( $renames as $item ) {
            if ( empty( $item['id'] ) || empty( $item['new_name'] ) ) {
                continue;
            }
            $id       = intval( $item['id'] );
            $new_name = sanitize_file_name( $item['new_name'] );
            $old_path = get_attached_file( $id );

            if ( ! $old_path || ! file_exists( $old_path ) ) {
                $results[ $id ] = [ 'success' => false, 'message' => 'File not found' ];
                continue;
            }

            $ext      = pathinfo( $old_path, PATHINFO_EXTENSION );
            $dir      = pathinfo( $old_path, PATHINFO_DIRNAME );
            $new_path = trailingslashit( $dir ) . $new_name . '.' . $ext;

            if ( file_exists( $new_path ) ) {
                $results[ $id ] = [ 'success' => false, 'message' => 'Target file exists' ];
                continue;
            }

            $moved = false;
            if ( $this->filesystem_initialized && isset( $wp_filesystem ) ) {
                $moved = $wp_filesystem->move( $old_path, $new_path, true );
            }
            if ( ! $moved ) {
                $moved = @rename( $old_path, $new_path );
            }

            if ( ! $moved ) {
                $results[ $id ] = [ 'success' => false, 'message' => 'Rename failed' ];
                continue;
            }

            update_attached_file( $id, $new_path );
            $metadata = wp_generate_attachment_metadata( $id, $new_path );
            if ( ! is_wp_error( $metadata ) ) {
                wp_update_attachment_metadata( $id, $metadata );
            }

            $old_url = wp_get_attachment_url( $id );
            $new_url = str_replace( wp_basename( $old_path ), wp_basename( $new_path ), $old_url );

            $post_data = [
                'ID'         => $id,
                'post_title' => pathinfo( $new_name, PATHINFO_FILENAME ),
                'post_name'  => sanitize_title( pathinfo( $new_name, PATHINFO_FILENAME ) ),
                'guid'       => esc_url_raw( $new_url ),
            ];
            wp_update_post( $post_data );

            $results[ $id ] = [ 'success' => true ];
        }

        wp_send_json_success( $results );
    }

    private function init_filesystem() {
        if ( $this->filesystem_initialized ) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if ( WP_Filesystem() ) {
            $this->filesystem_initialized = true;
        }
    }

    private function call_ai_service( $payload ) {
        $endpoint = apply_filters( 'bsmr_ai_endpoint', '' );
        if ( empty( $endpoint ) ) {
            throw new Exception( 'AI endpoint not configured' );
        }

        $response = wp_remote_post( $endpoint, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'JSON decode error: ' . json_last_error_msg() . ' Response: ' . $body );
        }

        if ( $code < 200 || $code >= 300 ) {
            throw new Exception( 'AI service error: ' . $body );
        }

        return $data;
    }
}

BSMR_AJAXActionsController::get_instance();