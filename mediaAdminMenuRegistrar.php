<?php

class MediaAdminMenuRegistrar {
    /**
     * Singleton instance.
     *
     * @var MediaAdminMenuRegistrar|null
     */
    private static $instance = null;

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Get singleton instance.
     *
     * @return MediaAdminMenuRegistrar
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bulk-smart-media-renamer',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        $capability  = 'manage_options';
        $parent_slug = 'bulk-smart-media-renamer';

        add_menu_page(
            __( 'Bulk Smart Media Renamer', 'bulk-smart-media-renamer' ),
            __( 'Media Renamer', 'bulk-smart-media-renamer' ),
            $capability,
            $parent_slug,
            array( $this, 'render_main_page' ),
            'dashicons-admin-media',
            60
        );

        add_submenu_page(
            $parent_slug,
            __( 'Preview Renaming', 'bulk-smart-media-renamer' ),
            __( 'Preview', 'bulk-smart-media-renamer' ),
            $capability,
            $parent_slug . '-preview',
            array( $this, 'render_preview_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'Settings', 'bulk-smart-media-renamer' ),
            __( 'Settings', 'bulk-smart-media-renamer' ),
            $capability,
            $parent_slug . '-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'Rename History', 'bulk-smart-media-renamer' ),
            __( 'History', 'bulk-smart-media-renamer' ),
            $capability,
            $parent_slug . '-history',
            array( $this, 'render_history_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $allowed_hooks = array(
            'toplevel_page_bulk-smart-media-renamer',
            'bulk-smart-media-renamer_page_bulk-smart-media-renamer-preview',
            'bulk-smart-media-renamer_page_bulk-smart-media-renamer-settings',
            'bulk-smart-media-renamer_page_bulk-smart-media-renamer-history',
        );

        if ( in_array( $hook_suffix, $allowed_hooks, true ) ) {
            $css_path = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
            $js_path  = plugin_dir_path( __FILE__ ) . 'assets/js/admin.js';

            $version = defined( 'BSMR_VERSION' ) ? BSMR_VERSION : ( file_exists( $css_path ) ? filemtime( $css_path ) : false );
            wp_enqueue_style(
                'bsmr-admin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
                array(),
                $version
            );

            $version = defined( 'BSMR_VERSION' ) ? BSMR_VERSION : ( file_exists( $js_path ) ? filemtime( $js_path ) : false );
            wp_enqueue_script(
                'bsmr-admin-js',
                plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
                array( 'jquery' ),
                $version,
                true
            );

            wp_localize_script(
                'bsmr-admin-js',
                'bsmrAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'bsmr_admin_nonce' ),
                )
            );
        }
    }

    /**
     * Render the main admin page.
     */
    public function render_main_page() {
        require_once plugin_dir_path( __FILE__ ) . 'views/main-page.php';
    }

    /**
     * Render the preview page.
     */
    public function render_preview_page() {
        require_once plugin_dir_path( __FILE__ ) . 'views/preview-page.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        require_once plugin_dir_path( __FILE__ ) . 'views/settings-page.php';
    }

    /**
     * Render the history page.
     */
    public function render_history_page() {
        require_once plugin_dir_path( __FILE__ ) . 'views/history-page.php';
    }
}

// Initialize.
MediaAdminMenuRegistrar::get_instance();