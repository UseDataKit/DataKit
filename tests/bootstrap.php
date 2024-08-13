<?php

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Polyfill for unit tests.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( ...$args ) {
		return $args[1];
	}
}
