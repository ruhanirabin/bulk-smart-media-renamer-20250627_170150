<?php
namespace BulkSmartMediaRenamer;

if ( ! defined( 'BSMR_PLUGIN_FILE' ) ) {
    define( 'BSMR_PLUGIN_FILE', __FILE__ );
}

// Activation hook.
if ( ! function_exists( __NAMESPACE__ . '\\activate' ) ) {
    function activate() {
        Activator::activate();
    }
}
register_activation_hook( BSMR_PLUGIN_FILE, __NAMESPACE__ . '\\activate' );

// Deactivation hook.
if ( ! function_exists( __NAMESPACE__ . '\\deactivate' ) ) {
    function deactivate() {
        Deactivator::deactivate();
    }
}
register_deactivation_hook( BSMR_PLUGIN_FILE, __NAMESPACE__ . '\\deactivate' );

// Uninstall hook.
if ( class_exists( __NAMESPACE__ . '\\Uninstaller' ) ) {
    if ( ! function_exists( __NAMESPACE__ . '\\uninstall' ) ) {
        function uninstall() {
            Uninstaller::uninstall();
        }
    }
    register_uninstall_hook( BSMR_PLUGIN_FILE, __NAMESPACE__ . '\\uninstall' );
}

// Initialize plugin after all plugins are loaded.
add_action( 'plugins_loaded', function() {
    $plugin = new Core();
    $plugin->run();
} );