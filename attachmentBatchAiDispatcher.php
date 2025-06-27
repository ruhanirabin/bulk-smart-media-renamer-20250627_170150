<?php

class Attachment_Batch_AI_Dispatcher {
    const ACTION_HOOK       = 'bsr_process_ai_batch';
    const OPTION_BATCH_SIZE = 'bsr_batch_size';
    const META_PROCESSED    = 'bsr_ai_processed';
    const AS_GROUP          = 'bsr-ai';

    public static function init() {
        add_action( self::ACTION_HOOK, [ __CLASS__, 'process_ai_batch' ], 10, 1 );
    }

    /**
     * Scan attachments in paginated batches and dispatch each batch for AI analysis.
     *
     * @param bool $full If true, scans all attachments. Otherwise only unprocessed.
     */
    public static function scan_attachments( $full = false ) {
        $batch_size = max( 1, (int) get_option( self::OPTION_BATCH_SIZE, 10 ) );
        $paged      = 1;

        while ( true ) {
            $query_args = [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => $batch_size,
                'paged'          => $paged,
                'fields'         => 'ids',
            ];

            if ( ! $full ) {
                $query_args['meta_query'] = [
                    [
                        'key'     => self::META_PROCESSED,
                        'compare' => 'NOT EXISTS',
                    ],
                ];
            }

            $query = new WP_Query( $query_args );
            $ids   = $query->posts;
            wp_reset_postdata();

            if ( empty( $ids ) ) {
                break;
            }

            self::dispatch_batch( $ids );

            if ( count( $ids ) < $batch_size ) {
                break;
            }

            $paged++;
        }
    }

    /**
     * Schedule a single action or WP-Cron event for the given batch of IDs,
     * avoiding duplicates and adding a small delay for reliability.
     *
     * @param int[] $ids Attachment post IDs.
     */
    protected static function dispatch_batch( array $ids ) {
        $args      = [ 'ids' => array_map( 'intval', $ids ) ];
        $timestamp = time() + 5; // slight delay to ensure cron picks it up

        if ( function_exists( 'as_schedule_single_action' ) ) {
            // Use Action Scheduler and avoid duplicate scheduling
            if ( ! as_has_scheduled_action( self::ACTION_HOOK, $args, self::AS_GROUP ) ) {
                as_schedule_single_action( $timestamp, self::ACTION_HOOK, $args, self::AS_GROUP );
            }
        } else {
            // Fallback to WP-Cron
            $next = wp_next_scheduled( self::ACTION_HOOK, [ $args ] );
            if ( ! $next ) {
                wp_schedule_single_event( $timestamp, self::ACTION_HOOK, [ $args ] );
            }
        }
    }

    /**
     * Process a batch of attachments: call AI client and mark as processed.
     *
     * @param array $args {
     *     @type int[] $ids Attachment post IDs.
     * }
     */
    public static function process_ai_batch( $args ) {
        if ( empty( $args['ids'] ) || ! is_array( $args['ids'] ) ) {
            return;
        }

        $ids = array_map( 'intval', $args['ids'] );
        AIClient::analyzeBatch( $ids );

        foreach ( $ids as $id ) {
            update_post_meta( $id, self::META_PROCESSED, 1 );
        }
    }
}

Attachment_Batch_AI_Dispatcher::init();
add_action( 'bsr_scan_attachments', [ 'Attachment_Batch_AI_Dispatcher', 'scan_attachments' ], 10, 1 );