<?php

namespace BitApps\WPTelemetry\Telemetry;

use BitApps\WPTelemetry\Telemetry\Feedback\Feedback;
use BitApps\WPTelemetry\Telemetry\Report\Report;

class Telemetry
{
    private static $report;

    private static $feedback;

    private static $version = '0.0.1';

    public static function getVersion()
    {
        return self::$version;
    }

    public static function report()
    {
        if (!self::$report) {
            self::$report = new Report();
        }

        return self::$report;
    }

    public static function feedback()
    {
        if (!self::$feedback) {
            self::$feedback = new Feedback();
        }

        return self::$feedback;
    }

    public static function view($fileName, $args)
    {
        load_template(\dirname(\dirname(__DIR__)) . '/src/views/' . $fileName . '.php', false, $args);
    }

    public static function sendReport($route, $data, $blocking = false)
    {
        $apiUrl = trailingslashit(TelemetryConfig::getServerBaseUrl()) . $route;

        $headers = [
            'host-user'    => md5(esc_url(home_url())),
            'Content-Type' => 'application/json',
        ];

        $body = wp_json_encode(array_merge($data, ['wp_telemetry' => self::$version]));
        
        // Debug logging
        error_log( '========================================' );
        error_log( '=== WP-Telemetry: sendReport() ===' );
        error_log( '========================================' );
        error_log( 'API URL: ' . $apiUrl );
        error_log( 'Route: ' . $route );
        error_log( 'Blocking: ' . ( $blocking ? 'Yes' : 'No' ) );
        error_log( 'Headers: ' . print_r( $headers, true ) );
        error_log( 'Data keys: ' . implode( ', ', array_keys( $data ) ) );
        error_log( 'Body length: ' . strlen( $body ) . ' bytes' );
        error_log( 'Body preview: ' . substr( $body, 0, 500 ) );

        $response = wp_remote_post(
            $apiUrl,
            [
                'method'      => 'POST',
                'timeout'     => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => $blocking,
                'headers'     => $headers,
                'body'        => $body,
                'cookies'     => [],
            ]
        );
        
        // Log response
        if ( is_wp_error( $response ) )
        {
            error_log( '❌ WP Error: ' . $response->get_error_message() );
            error_log( 'Error code: ' . $response->get_error_code() );
        } else
        {
            $responseCode = wp_remote_retrieve_response_code( $response );
            $responseBody = wp_remote_retrieve_body( $response );
            error_log( '✅ Response Code: ' . $responseCode );
            error_log( 'Response Body: ' . substr( $responseBody, 0, 500 ) );
        }
        
        error_log( '========================================' );

        return $response;
    }
}
