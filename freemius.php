<?php

/** If this file is called directly, abort. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( function_exists( 'datakit_licensing' ) ) {
	// Auto-deactivate the free version when activating the paid one.
	datakit_licensing()->set_basename( true, __FILE__ );

	return;
}

// Create a helper function for easy SDK access.
function datakit_licensing() {
	global $datakit_licensing;

	if ( ! isset( $datakit_licensing ) ) {
		// Activate multisite network integration.
		if ( ! defined( 'WP_FS__PRODUCT_16272_MULTISITE' ) ) {
			define( 'WP_FS__PRODUCT_16272_MULTISITE', true );
		}

		// Include Freemius SDK.
		require_once dirname( __FILE__ ) . '/freemius/start.php';

		$datakit_licensing = fs_dynamic_init( [
			'id'                  => '16272',
			'slug'                => 'datakit',
			'premium_slug'        => 'datakit-pro',
			'type'                => 'plugin',
			'public_key'          => 'pk_17189421b1fd7937c94606394609e',
			'is_premium'          => true,
			'premium_suffix'      => 'Pro',
			// If your plugin is a serviceware, set this option to false.
			'has_premium_version' => true,
			'has_addons'          => false,
			'has_paid_plans'      => true,
			'trial'               => [
				'days'               => 30,
				'is_require_payment' => false,
			],
			'menu'                => [
				'first-path' => 'plugins.php',
				'contact'    => true,
				'support'    => true,
			],
		] );
	}

	return $datakit_licensing;
}

// Init Freemius.
datakit_licensing();

// Signal that SDK was initiated.
do_action( 'datakit_licensing_loaded' );
