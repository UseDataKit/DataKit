<?php
/**
 * Plugin Name:         DataKit
 * Description:         Easily create your own DataViews components with just PHP.
 * Plugin URI:          https://www.datakit.org
 * Version:             1.0.0
 * Author:              DataKit
 * Author URI:          https://www.datakit.org
 * Text Domain:         datakit
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.html
 */

use DataKit\Plugin\DataView\WordPressDataViewRepository;
use DataKit\Plugin\DataKitPlugin;

/** If this file is called directly, abort. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';

const DATAKIT_PLUGIN_FILE = __FILE__;
const DATAKIT_VERSION     = '1.0.0';

// Initialize the plugin.
add_action(
	'init',
	function () {
		try {
			DataKitPlugin::get_instance(
				new WordPressDataViewRepository(),
			);
		} catch ( Throwable $e ) {
			// Todo: log errors somewhere.
			return;
		}
	},
);
