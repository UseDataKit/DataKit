<?php

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Polyfill for unit tests.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( ...$args ) {
		return $args[1];
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( ...$args ) {
		return $args[0];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( ...$args ) {
		return $args[0];
	}
}
