<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BSMR_Admin_Assets {

    /**
     * @var string Version for admin JS.
     */
    private static $js_version;

    /**
     * @var string Version for admin CSS.
     */
    private static $css_version;

    /**
     * @var string Base URL to the plugin root.
     */
    private static $url_base;

    /**
     * Initialize asset versions and hook into admin page load.
     */
    public static function init() {
        if ( ! is_admin() ) {
            return;
        }

        $suffix       = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        $js_relative  = "assets/js/admin{$suffix}.js";
        $css_relative = "assets/css/admin{$suffix}.css";

        // Determine plugin file paths
        $plugin_root = plugin_dir_path( dirname( __FILE__ ) );
        $js_file     = $plugin_root . $js_relative;
        $css_file    = $plugin_root . $css_relative;

        // Determine version strings
        if ( defined( 'BSMR_VERSION' ) ) {
            self::$js_version  = BSMR_VERSION;
            self::$css_version = BSMR_VERSION;
        } else {
            self::$js_version  = file_exists( $js_file )  ? filemtime( $js_file )  : '1.0.0';
            self::$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
        }

        // Determine plugin URL base (one level up from this includes folder)
        $includes_url   = plugin_dir_url( dirname( __FILE__ ) );
        self::$url_base = untrailingslashit( dirname( $includes_url ) );

        // Enqueue assets only on our Bulk Smart Media Renamer admin page
        add_action( 'load-media_page_bulk-smart-media-renamer', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue admin CSS and JS, and localize script data.
     */
    public static function enqueue_assets() {
        $suffix     = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        $js_handle  = 'bsmr-admin-js';
        $css_handle = 'bsmr-admin-css';

        $js_url  = self::$url_base . "/assets/js/admin{$suffix}.js";
        $css_url = self::$url_base . "/assets/css/admin{$suffix}.css";

        wp_enqueue_style( $css_handle, $css_url, array(), self::$css_version );
        wp_enqueue_script( $js_handle, $js_url, array( 'jquery' ), self::$js_version, true );

        wp_localize_script(
            $js_handle,
            'BSMR_Admin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'bsmr_admin_action' ),
                'i18n'    => array(
                    'confirmBulkRename'  => __( 'Are you sure you want to rename the selected media items?', 'bulk-smart-media-renamer' ),
                    'renamingInProgress' => __( 'Renaming in progress?', 'bulk-smart-media-renamer' ),
                    'renameCompleted'    => __( 'Renaming completed.', 'bulk-smart-media-renamer' ),
                    'errorOccurred'      => __( 'An error occurred. Please try again.', 'bulk-smart-media-renamer' ),
                ),
            )
        );
    }
}

BSMR_Admin_Assets::init();