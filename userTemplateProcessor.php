<?php

class UserTemplateProcessor {
    public static function generate( $template, array $tokens ) {
        // Prepare search and replace arrays.
        $search  = array_keys( $tokens );
        $replace = array_map( 'strval', array_values( $tokens ) );

        // Replace tokens in the template.
        $filename = str_replace( $search, $replace, $template );

        // Sanitize to remove illegal characters.
        $filename = sanitize_file_name( $filename );

        // Ensure there's a valid base name; fallback to 'file' if sanitization stripped everything.
        $info      = pathinfo( $filename );
        $base      = isset( $info['filename'] ) ? $info['filename'] : '';
        $extension = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
        if ( '' === $base ) {
            $filename = 'file' . $extension;
        }

        // Use WP core to ensure the filename is unique in the uploads directory.
        $upload_dir = wp_upload_dir();
        $dir_path   = trailingslashit( $upload_dir['path'] );
        return wp_unique_filename( rtrim( $dir_path, '/' ), $filename );
    }
}