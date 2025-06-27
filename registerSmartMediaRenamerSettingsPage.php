function smr_add_settings_page() {
    add_submenu_page(
        'upload.php',
        __( 'Smart Media Renamer', 'bulk-smart-media-renamer' ),
        __( 'Smart Media Renamer', 'bulk-smart-media-renamer' ),
        'manage_options',
        'smr_settings',
        'smr_render_settings_page'
    );
}
add_action( 'admin_menu', 'smr_add_settings_page' );

/**
 * Registers settings, sections, and fields.
 */
function smr_register_settings() {
    register_setting(
        'smr_settings',
        'smr_api_key',
        'sanitize_text_field'
    );

    add_settings_section(
        'smr_api_section',
        __( 'API Settings', 'bulk-smart-media-renamer' ),
        'smr_api_section_callback',
        'smr_settings'
    );

    add_settings_field(
        'smr_api_key',
        __( 'API Key', 'bulk-smart-media-renamer' ),
        'smr_render_api_key_field',
        'smr_settings',
        'smr_api_section'
    );
}
add_action( 'admin_init', 'smr_register_settings' );

/**
 * Section callback: description for the API settings section.
 */
function smr_api_section_callback() {
    echo '<p>' . esc_html__( 'Enter your API key to enable AI-driven bulk renaming of media files.', 'bulk-smart-media-renamer' ) . '</p>';
}

/**
 * Renders the API key input field.
 */
function smr_render_api_key_field() {
    $value = get_option( 'smr_api_key', '' );
    printf(
        '<input type="text" id="smr_api_key" name="smr_api_key" value="%s" class="regular-text" />',
        esc_attr( $value )
    );
}

/**
 * Renders the settings page content.
 */
function smr_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Smart Media Renamer Settings', 'bulk-smart-media-renamer' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'smr_settings' );
            do_settings_sections( 'smr_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}