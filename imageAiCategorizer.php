<?php

class ImageAiCategorizer {
    const OPTION_ENDPOINT = 'ai_categorizer_endpoint';
    const OPTION_API_KEY  = 'ai_categorizer_api_key';

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_hooks' ] );
    }

    /**
     * Register custom hooks for asynchronous processing.
     */
    public static function register_hooks() {
        add_action( 'ai_categorizer_process_attachment', [ __CLASS__, 'process_attachment' ], 10, 1 );
    }

    /**
     * Analyze a batch of attachments by scheduling or sending them to the AI service.
     *
     * @param int[] $attachment_ids Array of attachment IDs.
     */
    public static function analyzeBatch( array $attachment_ids ) {
        if ( empty( $attachment_ids ) ) {
            return;
        }

        $has_async = function_exists( 'as_enqueue_async_action' ) || class_exists( 'ActionScheduler' );

        foreach ( $attachment_ids as $attachment_id ) {
            $attachment_id = absint( $attachment_id );
            if ( ! $attachment_id ) {
                continue;
            }

            if ( $has_async ) {
                // WP Async Actions (if available).
                if ( function_exists( 'as_enqueue_async_action' ) ) {
                    as_enqueue_async_action( 'ai_categorizer_process_attachment', [ $attachment_id ] );
                }
                // ActionScheduler (if available).
                elseif ( class_exists( 'ActionScheduler' ) ) {
                    ActionScheduler::schedule_single_action( time(), 'ai_categorizer_process_attachment', [ $attachment_id ], 'ai_categorizer' );
                }
            } else {
                // Fallback: synchronous processing.
                self::process_attachment( $attachment_id );
            }
        }
    }

    /**
     * Process a single attachment: send to AI service and handle response.
     *
     * @param int $attachment_id Attachment ID.
     */
    public static function process_attachment( $attachment_id ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) {
            return;
        }

        $url  = wp_get_attachment_url( $attachment_id );
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! $url || ! is_array( $meta ) ) {
            return;
        }

        // Prune metadata to only required fields.
        $pruned_meta = [
            'file_name' => wp_basename( $url ),
            'width'     => isset( $meta['width'] ) ? intval( $meta['width'] ) : 0,
            'height'    => isset( $meta['height'] ) ? intval( $meta['height'] ) : 0,
        ];

        $file_path = get_attached_file( $attachment_id );
        if ( $file_path && file_exists( $file_path ) ) {
            $pruned_meta['file_size'] = filesize( $file_path );
        }

        $endpoint = get_option( self::OPTION_ENDPOINT, '' );
        $api_key  = get_option( self::OPTION_API_KEY, '' );
        if ( empty( $endpoint ) ) {
            error_log( 'ImageAiCategorizer: API endpoint not configured.' );
            return;
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ( ! empty( $api_key ) ) {
            $headers['Authorization'] = 'Bearer ' . sanitize_text_field( $api_key );
        }

        $response = wp_remote_post(
            esc_url_raw( $endpoint ),
            [
                'headers' => $headers,
                'body'    => wp_json_encode(
                    [
                        'file' => esc_url_raw( $url ),
                        'meta' => $pruned_meta,
                    ]
                ),
                'timeout' => 60,
            ]
        );

        self::process_response( $attachment_id, $response );
    }

    /**
     * Process the AI service response for an attachment.
     *
     * @param int                   $attachment_id Attachment ID.
     * @param WP_Error|array|string $response      Response from wp_remote_post.
     */
    protected static function process_response( $attachment_id, $response ) {
        if ( is_wp_error( $response ) ) {
            error_log( sprintf( 'AI Categorizer error for attachment %d: %s', $attachment_id, $response->get_error_message() ) );
            return;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== intval( $status ) ) {
            error_log( sprintf( 'AI Categorizer HTTP %d for attachment %d', $status, $attachment_id ) );
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            error_log( sprintf( 'AI Categorizer invalid JSON for attachment %d', $attachment_id ) );
            return;
        }

        // Assign categories if provided.
        if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
            $terms = array_map( 'sanitize_text_field', $data['categories'] );
            wp_set_object_terms( $attachment_id, $terms, 'media_category', false );
        }

        // Update post title if provided.
        if ( ! empty( $data['title'] ) ) {
            $new_title = sanitize_text_field( $data['title'] );
            wp_update_post(
                [
                    'ID'         => $attachment_id,
                    'post_title' => $new_title,
                ]
            );
        }
    }
}

ImageAiCategorizer::init();