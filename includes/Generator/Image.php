<?php
/**
 * Image generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.1.0
 */

namespace ContentForge\Generator;

use ContentForge\Settings\AI_Settings_Manager;
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

            $attachment_id = $this->sideload( $tmp_file, $filename, $title );

            if ( ! is_wp_error( $attachment_id ) ) {
                $ids[] = $attachment_id;
            }
        }

        return $ids;
    }

    /**
     * Generate a single image with the configured AI provider.
     *
     * Falls back to nothing — the caller decides whether to retry with the
     * placeholder path.
     *
     * @since 1.8.0
     *
     * @param string $prompt Image prompt.
     * @param string $title  Post title, used for the filename and alt text.
     * @return int|\WP_Error Attachment ID, or WP_Error on failure.
     */
    public function generate_from_ai( $prompt, $title )
    {
        $provider_slug = AI_Settings_Manager::get_active_provider();
        $api_key       = AI_Settings_Manager::get_api_key( $provider_slug );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'cforge_no_api_key', __( 'No API key configured for the active AI provider.', 'content-forge' ) );
        }

        $provider = AI_Content_Generator::make_provider(
            $provider_slug,
            AI_Settings_Manager::get_active_model(),
            $api_key
        );

        if ( ! $provider->supports_images() ) {
            return new WP_Error(
                'cforge_no_image_support',
                __( 'The active AI provider does not support image generation.', 'content-forge' )
            );
        }

        $tmp_file = $provider->generate_image( $prompt );

        if ( is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filename = 'ai-' . sanitize_title( $title ) . '-' . wp_rand( 1000, 9999 ) . '.png';

        return $this->sideload( $tmp_file, $filename, $title );
    }

    /**
     * Move a downloaded temp file into the Media Library and track it.
     *
     * Shared by the placeholder and AI paths so tracking, alt text and temp-file
     * cleanup only exist once.
     *
     * @since 1.8.0
     *
     * @param string $tmp_file Absolute path to the temporary file.
     * @param string $filename Target filename.
     * @param string $title    Title used for alt text.
     * @return int|\WP_Error Attachment ID, or WP_Error on failure.
     */
    protected function sideload( $tmp_file, $filename, $title )
    {
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        ];

        $attachment_id = media_handle_sideload( $file_array, 0 );

        if ( ! is_wp_error( $attachment_id ) ) {
            $this->track_generated( $attachment_id, 'attachment' );

            if ( '' !== (string) $title ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
            }
        }

        // Clean up temporary file if it still exists (media_handle_sideload should handle it, but good practice)
        if ( file_exists( $tmp_file ) ) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink( $tmp_file );
        }

        return $attachment_id;
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
