<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BulkSmartMediaRenamer {
    private static $instance = null;
    private $option_name = 'bsr_settings';

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bsr_scan_attachments', array( $this, 'ajax_scan_attachments' ) );
        add_action( 'wp_ajax_bsr_generate_filenames', array( $this, 'ajax_generate_filenames' ) );
        add_action( 'wp_ajax_bsr_rename_files', array( $this, 'ajax_rename_files' ) );
    }

    public function activate() {
        $defaults = array(
            'api_key'           => '',
            'filename_template' => '{description}-{date}',
        );
        add_option( $this->option_name, $defaults );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Bulk Smart Media Renamer',
            'Media Renamer',
            'manage_options',
            'bsr_settings',
            array( $this, 'options_page' ),
            'dashicons-edit',
            80
        );
    }

    public function settings_init() {
        register_setting( 'bsr_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
        add_settings_section( 'bsr_api_section', 'API Settings', null, 'bsr_settings' );
        add_settings_field( 'bsr_api_key', 'AI Service API Key', array( $this, 'render_api_key' ), 'bsr_settings', 'bsr_api_section' );
        add_settings_field( 'bsr_filename_template', 'Filename Template', array( $this, 'render_filename_template' ), 'bsr_settings', 'bsr_api_section' );
    }

    public function sanitize_settings( $input ) {
        $output = get_option( $this->option_name );
        $output['api_key']           = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $output['filename_template'] = isset( $input['filename_template'] ) ? sanitize_text_field( $input['filename_template'] ) : $output['filename_template'];
        return $output;
    }

    public function render_api_key() {
        $options = get_option( $this->option_name );
        printf(
            '<input type="text" name="%s[api_key]" value="%s" style="width:400px;" />',
            esc_attr( $this->option_name ),
            esc_attr( $options['api_key'] )
        );
    }

    public function render_filename_template() {
        $options = get_option( $this->option_name );
        printf(
            '<input type="text" name="%s[filename_template]" value="%s" style="width:400px;" />',
            esc_attr( $this->option_name ),
            esc_attr( $options['filename_template'] )
        );
        echo '<p class="description">Use placeholders like {description}, {date}, {title}</p>';
    }

    public function options_page() {
        ?>
        <div class="wrap">
            <h1>Bulk Smart Media Renamer</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'bsr_settings_group' );
                do_settings_sections( 'bsr_settings' );
                submit_button();
                ?>
            </form>
            <hr />
            <h2>Bulk Rename Media</h2>
            <button id="bsr-scan" class="button button-primary">Scan Media</button>
            <div id="bsr-results"></div>
        </div>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_bsr_settings' ) {
            return;
        }
        wp_enqueue_script( 'bsr-script', plugin_dir_url( __FILE__ ) . 'assets/bsr.js', array( 'jquery' ), '1.0.0', true );
        wp_localize_script( 'bsr-script', 'bsrData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bsr_nonce' ),
        ) );
        wp_enqueue_style( 'bsr-style', plugin_dir_url( __FILE__ ) . 'assets/bsr.css', array(), '1.0.0' );
    }

    public function ajax_scan_attachments() {
        check_ajax_referer( 'bsr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $attachments = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => -1 ) );
        $data        = array();
        foreach ( $attachments as $att ) {
            $file = get_attached_file( $att->ID );
            $data[] = array(
                'id'                => $att->ID,
                'original_filename' => basename( $file ),
                'title'             => get_the_title( $att->ID ),
                'description'       => wp_get_attachment_caption( $att->ID ),
                'date'              => get_the_date( 'Y-m-d', $att->ID ),
            );
        }
        wp_send_json_success( $data );
    }

    public function ajax_generate_filenames() {
        check_ajax_referer( 'bsr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $items       = isset( $_POST['items'] ) ? $_POST['items'] : array();
        $options     = get_option( $this->option_name );
        $api_key     = $options['api_key'];
        $template    = $options['filename_template'];
        $suggestions = array();
        foreach ( $items as $item ) {
            $prompt = "Generate an SEO-friendly filename for an image with title: {$item['title']}, description: {$item['description']}, date: {$item['date']}. Use this template: {$template}.";
            $response = wp_remote_post( 'https://api.example.com/generate-filename', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'prompt' => $prompt ) ),
                'timeout' => 60,
            ) );
            if ( is_wp_error( $response ) ) {
                $suggestions[] = array( 'id' => $item['id'], 'error' => $response->get_error_message() );
                continue;
            }
            $body   = wp_remote_retrieve_body( $response );
            $result = json_decode( $body, true );
            $filename = sanitize_file_name( $result['filename'] ?? '' );
            $suggestions[] = array( 'id' => $item['id'], 'suggested' => $filename );
        }
        wp_send_json_success( $suggestions );
    }

    public function ajax_rename_files() {
        check_ajax_referer( 'bsr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $items   = isset( $_POST['items'] ) ? $_POST['items'] : array();
        $results = array();
        foreach ( $items as $item ) {
            $id          = intval( $item['id'] );
            $new_name    = sanitize_file_name( $item['suggested'] );
            $current_file = get_attached_file( $id );
            $info        = pathinfo( $current_file );
            $new_file    = $info['dirname'] . '/' . $new_name . '.' . $info['extension'];
            if ( @rename( $current_file, $new_file ) ) {
                update_attached_file( $id, $new_file );
                $metadata = wp_generate_attachment_metadata( $id, $new_file );
                wp_update_attachment_metadata( $id, $metadata );
                $results[] = array( 'id' => $id, 'success' => true, 'new_filename' => basename( $new_file ) );
            } else {
                $results[] = array( 'id' => $id, 'success' => false );
            }
        }
        wp_send_json_success( $results );
    }
}

BulkSmartMediaRenamer::get_instance();