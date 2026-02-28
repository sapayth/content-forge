<?php

namespace ContentForge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation tasks.
 *
 * @since 1.4.0
 */
class Deactivator {

    /**
     * Send a deactivation payload to the telemetry server.
     *
     * @since 1.4.0
     */
    public static function deactivate() {
        // Only run if wp_remote_post is available
        if ( ! function_exists( 'wp_remote_post' ) ) {
            return;
        }

        // Apply filters to allow overriding the server URL if needed.
        $server_url = apply_filters( 'cforge_telemetry_server_url', 'https://feedio.sapayth.com/api/' );
        $endpoint   = rtrim( $server_url, '/' ) . '/plugin-deactivate';

        $body = [
            'url'         => home_url(),
            'plugin_slug' => 'content-forge',
        ];

        // Send a non-blocking request to the telemetry server
        wp_remote_post( $endpoint, [
            'timeout'   => 2,
            'blocking'  => false,
            'sslverify' => false,
            'body'      => $body,
        ] );
    }
}
