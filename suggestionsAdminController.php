<?php
class Smr_Suggestions_Admin_Controller {
    const NONCE_ACTION = 'smr_fetch';
    const NONCE_FIELD  = 'nonce';
    const PAGE_SLUG    = 'smr-review';
    const PER_PAGE     = 50;

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_review_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_smr_fetch', [ __CLASS__, 'fetch_suggestions' ] );
    }

    public static function register_review_page() {
        add_submenu_page(
            'upload.php',
            __( 'Smart Media Renamer', 'smr' ),
            __( 'Smart Media Renamer', 'smr' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_review_page' ]
        );
    }

    public static function enqueue_assets( $hook_suffix ) {
        if ( $hook_suffix !== 'media_page_' . self::PAGE_SLUG ) {
            return;
        }
        $plugin_root_dir  = dirname( dirname( __FILE__ ) );
        $plugin_root_url  = plugin_dir_url( $plugin_root_dir );
        $plugin_root_path = plugin_dir_path( $plugin_root_dir );

        // Use existing bulk-renamer-admin assets instead of missing smr-review files
        $js_file    = 'assets/js/bulk-renamer-admin.js';
        $js_path    = $plugin_root_path . $js_file;
        $js_url     = $plugin_root_url . $js_file;
        $js_version = file_exists( $js_path ) ? filemtime( $js_path ) : false;

        wp_enqueue_script(
            'smr-review-js',
            $js_url,
            [ 'jquery' ],
            $js_version,
            true
        );
        wp_localize_script(
            'smr-review-js',
            'SMR_Ajax',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
            ]
        );

        $css_file    = 'assets/css/bulk-renamer-admin.css';
        $css_path    = $plugin_root_path . $css_file;
        $css_url     = $plugin_root_url . $css_file;
        $css_version = file_exists( $css_path ) ? filemtime( $css_path ) : false;

        wp_enqueue_style(
            'smr-review-css',
            $css_url,
            [],
            $css_version
        );
    }

    public static function render_review_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Bulk Smart Media Renamer', 'smr' ) . '</h1>';
        echo '<div id="smr-review"></div>';
        echo '</div>';
    }

    public static function fetch_suggestions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smr' ), 403 );
        }
        check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $paged    = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
        $paged    = max( 1, $paged );
        $per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : self::PER_PAGE;
        $per_page = ( $per_page > 0 && $per_page <= 1000 ) ? $per_page : self::PER_PAGE;

        $result = self::suggestions( $paged, $per_page );
        wp_send_json_success( $result );
    }

    protected static function suggestions( $paged = 1, $per_page = self::PER_PAGE ) {
        $total_posts = wp_count_posts( 'attachment' );
        $total_count = isset( $total_posts->inherit ) ? intval( $total_posts->inherit ) : 0;
        $total_pages = ( $per_page > 0 ) ? ceil( $total_count / $per_page ) : 0;

        $args = [
            'post_type'      => 'attachment',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'post_status'    => 'inherit',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];
        $query   = new WP_Query( $args );
        $results = [];

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $attachment ) {
                $file_path          = get_attached_file( $attachment->ID );
                $current_filename   = basename( $file_path );
                $base_name          = pathinfo( $current_filename, PATHINFO_FILENAME );
                $extension          = pathinfo( $current_filename, PATHINFO_EXTENSION );
                $suggested_filename = sanitize_title( $base_name ) . '-optimized.' . $extension;

                $results[] = [
                    'id'               => $attachment->ID,
                    'current_filename' => $current_filename,
                    'suggested'        => $suggested_filename,
                ];
            }
            wp_reset_postdata();
        }

        return [
            'data'       => $results,
            'pagination' => [
                'total'       => $total_count,
                'per_page'    => $per_page,
                'paged'       => $paged,
                'total_pages' => $total_pages,
            ],
        ];
    }
}

Smr_Suggestions_Admin_Controller::init();