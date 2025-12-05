<?php
/**
 * Image generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.1.0
 */

namespace ContentForge\Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generator for featured images.
 */
class Image extends Generator {



    /**
     * Generate featured images.
     *
     * @param int   $count Number of images to generate.
     * @param array $args  Arguments array.
     *                     - title: string (for Placehold.co text)
     *                     - sources: array (e.g., ['picsum', 'placehold'])
     *
     * @return array Array of generated attachment IDs.
     */
    public function generate( $count = 1, $args = [] )
    {
        $ids     = [];
        $sources = isset( $args['sources'] ) && is_array( $args['sources'] ) ? $args['sources'] : [ 'picsum' ];
        $title   = isset( $args['title'] ) ? $args['title'] : 'Image';

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        for ( $i = 0; $i < $count; $i++ ) {
            // Randomly pick a source
            $source    = $sources[ array_rand( $sources ) ];
            $image_url = '';
            $filename  = '';

            if ( 'placehold' === $source ) {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
                $encoded_title = urlencode( $title );
                $image_url     = "https://placehold.co/800x600/png?text={$encoded_title}";
                $filename      = 'placehold-' . sanitize_title( $title ) . '-' . wp_rand( 1000, 9999 ) . '.png';
            } else {
                // Default to Picsum
                $image_url = 'https://picsum.photos/800/600';
                $filename  = 'picsum-' . wp_rand( 1000, 9999 ) . '.jpg';
            }

            // Download the image
            $tmp_file = download_url( $image_url );

            if ( is_wp_error( $tmp_file ) ) {
                continue;
            }

            $file_array = [
                'name'     => $filename,
                'tmp_name' => $tmp_file,
            ];

            // Upload to Media Library
            $attachment_id = media_handle_sideload( $file_array, 0 );

            if ( ! is_wp_error( $attachment_id ) ) {
                $ids[] = $attachment_id;
                $this->track_generated( $attachment_id, 'attachment' );
            }

            // Clean up temporary file if it still exists (media_handle_sideload should handle it, but good practice)
            if ( file_exists( $tmp_file ) ) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
                @unlink( $tmp_file );
            }
        }

        return $ids;
    }

    /**
     * Delete generated attachments.
     *
     * @param array $object_ids Array of attachment IDs to delete.
     *
     * @return int Number of items deleted.
     */
    public function delete( array $object_ids )
    {
        $deleted = 0;
        foreach ( $object_ids as $id ) {
            if ( wp_delete_attachment( $id, true ) ) {
                ++$deleted;
                $this->untrack_generated( $id, 'attachment' );
            }
        }
        return $deleted;
    }
}
