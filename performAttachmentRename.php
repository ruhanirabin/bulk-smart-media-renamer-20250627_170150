<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure HistoryLogger is loaded
if ( ! class_exists( 'HistoryLogger' ) ) {
    require_once __DIR__ . '/HistoryLogger.php';
}

class PerformAttachmentRename {

    public static function applyAll( array $list ) {
        global $wpdb, $wp_filesystem;

        // Ensure WP Filesystem is initialized
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }

        $upload_dir = wp_upload_dir();

        foreach ( $list as $item ) {
            $attachment_id = intval( $item['id'] );
            $old_basename  = sanitize_file_name( wp_unslash( $item['old'] ) );
            $new_basename  = sanitize_file_name( wp_unslash( $item['new'] ) );
            $has_moved     = false;
            $original_path = '';
            $new_path      = '';

            try {
                HistoryLogger::log( $old_basename, $new_basename );

                // Get current file path
                $file_path = get_attached_file( $attachment_id );
                if ( ! $file_path || ! $wp_filesystem->exists( $file_path ) ) {
                    throw new Exception( "Attachment file not found: {$file_path}" );
                }
                $original_path = $file_path;

                $old_filename = wp_basename( $file_path );
                $extension    = pathinfo( $old_filename, PATHINFO_EXTENSION );
                $new_filename = $new_basename . '.' . $extension;
                $directory    = dirname( $file_path );

                // Ensure filename is unique in the directory
                $unique_filename = wp_unique_filename( $directory, $new_filename );
                $new_path        = trailingslashit( $directory ) . $unique_filename;

                // Move file via WP Filesystem
                if ( $file_path !== $new_path ) {
                    if ( ! $wp_filesystem->move( $file_path, $new_path, true ) ) {
                        throw new Exception( "Failed to move file from {$file_path} to {$new_path}" );
                    }
                    $has_moved = true;
                }

                // Update the _wp_attached_file meta (relative path)
                $relative_old = ltrim( str_replace( trailingslashit( $upload_dir['basedir'] ), '', $original_path ), '/\\' );
                $relative_new = ltrim( str_replace( trailingslashit( $upload_dir['basedir'] ), '', $new_path ), '/\\' );
                update_post_meta( $attachment_id, '_wp_attached_file', $relative_new );

                // Update post title and enforce unique slug
                $title       = pathinfo( $unique_filename, PATHINFO_FILENAME );
                $post        = get_post( $attachment_id );
                $raw_slug    = sanitize_title( $title );
                $unique_slug = wp_unique_post_slug(
                    $raw_slug,
                    $attachment_id,
                    $post->post_status,
                    $post->post_type,
                    $post->post_parent
                );
                wp_update_post( array(
                    'ID'        => $attachment_id,
                    'post_title'=> $title,
                    'post_name' => $unique_slug,
                ) );

                // Regenerate attachment metadata (thumbnails, etc.)
                $metadata = wp_generate_attachment_metadata( $attachment_id, $new_path );
                if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
                    throw new Exception( "Failed to generate metadata for attachment {$attachment_id}" );
                }
                wp_update_attachment_metadata( $attachment_id, $metadata );

                // Replace references in content and postmeta
                $old_url      = trailingslashit( $upload_dir['baseurl'] ) . $relative_old;
                $new_url      = trailingslashit( $upload_dir['baseurl'] ) . $relative_new;
                $like_old_url = '%' . $wpdb->esc_like( $old_url ) . '%';

                // post_content
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->posts} 
                         SET post_content = REPLACE(post_content, %s, %s) 
                         WHERE post_content LIKE %s",
                        $old_url,
                        $new_url,
                        $like_old_url
                    )
                );

                // postmeta.meta_value
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} 
                         SET meta_value = REPLACE(meta_value, %s, %s) 
                         WHERE meta_value LIKE %s",
                        $old_url,
                        $new_url,
                        $like_old_url
                    )
                );

            } catch ( Exception $e ) {
                HistoryLogger::error( $e->getMessage() );

                // Rollback moved file if needed
                if ( $has_moved && $wp_filesystem->exists( $new_path ) ) {
                    $wp_filesystem->move( $new_path, $original_path, true );
                }

                continue;
            }
        }
    }
}