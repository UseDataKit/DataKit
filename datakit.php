<?php
/**
 * Plugin Name:         DataKit
 * Description:         Easily create your own DataViews components with just PHP.
 * Plugin URI:          https://www.datakit.org
 * Version:             0.1.0
 * Author:              DataKit
 * Author URI:          https://www.datakit.org
 * Text Domain:         dk-datakit
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.html
 */

use DataKit\Plugin\DataView\WordPressDataViewRepository;
use DataKit\Plugin\DataViewPlugin;

/** If this file is called directly, abort. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';

const DATAVIEW_PLUGIN_PATH = __FILE__;
const DATAVIEW_VERSION     = '0.1.0';

// Initialize the plugin.
add_action(
	'init',
	function () {
		try {
			DataViewPlugin::get_instance(
				new WordPressDataViewRepository(),
			);
		} catch ( Throwable $e ) {
			// Todo: log errors somewhere.
			return;
		}
	},
);
