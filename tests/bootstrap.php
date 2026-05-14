<?php
// DESCRIPTION: Test bootstrap — defines ABSPATH so plugin files load outside WP,
// and pulls in the Composer autoloader.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$autoload = __DIR__ . '/../vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Composer autoloader not found. Run `composer install` first.\n" );
	exit( 1 );
}

require_once $autoload;
