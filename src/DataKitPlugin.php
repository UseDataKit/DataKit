<?php

namespace DataKit\Plugin;

use DataKit\DataViews\AccessControl\AccessControlManager;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewRepository;
use DataKit\DataViews\DataView\Pagination;
use DataKit\Plugin\AccessControl\WordPressAccessController;
use DataKit\Plugin\Rest\Router;
use DataKit\Plugin\Component\DataViewShortcode;

/**
 * Entry point for the plugin.
 *
 * @since $ver$
 */
final class DataKitPlugin {
	/**
	 * The singleton plugin instance.
	 *
	 * @since $ver$
	 *
	 * @var self
	 */
	private static self $instance;

	/**
	 * The DataView repository.
	 *
	 * @since $ver$
	 *
	 * @var DataViewRepository
	 */
	private DataViewRepository $data_view_repository;

	/**
	 * Creates the plugin.
	 *
	 * @since $ver$
	 *
	 * @param DataViewRepository $data_view_repository The DataView repository.
	 */
	private function __construct( DataViewRepository $data_view_repository ) {
		$this->data_view_repository = $data_view_repository;

		do_action( 'datakit/loading' );

		AccessControlManager::set( new WordPressAccessController( wp_get_current_user() ) );
		Router::get_instance( $this->data_view_repository );
		DataViewShortcode::get_instance( $this->data_view_repository, AccessControlManager::current() );

		/**
		 * Modifies the default amount of results per page.
		 *
		 * @filter `datakit/dataview/pagination/per-page-default`
		 *
		 * @since  $ver$
		 *
		 * @param int $per_page The amount of results per page (default is 25).
		 */
		$per_page = (int) apply_filters( 'datakit/dataview/pagination/per-page-default', 25 );
		Pagination::default_results_per_page( $per_page );

		add_action( 'datakit/dataview/register', [ $this, 'register_data_view' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );

		do_action( 'datakit/loaded' );
	}

	/**
	 * Registers a DataView on the repository.
	 *
	 * @since $ver$
	 *
	 * @param DataView $data_view The DataView.
	 */
	public function register_data_view( DataView $data_view ): void {
		$this->data_view_repository->save( $data_view );
	}

	/**
	 * Register the scripts and styles.
	 *
	 * @since $ver$
	 */
	public function register_scripts(): void {
		$assets_dir = plugin_dir_url( DATAKIT_PLUGIN_FILE );

		wp_register_script( 'datakit/dataview', $assets_dir . 'assets/js/dataview.js', [], DATAKIT_VERSION, true );

		wp_register_style(
			'datakit/dataview',
			$assets_dir . 'assets/css/dataview.css',
			[ 'dashicons' ],
			DATAKIT_VERSION,
		);

		$fetch_options = [
			'headers' => [
				'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ),
			],
		];

		/**
		 * Modifies the default options passed to the `fetch` calls.
		 *
		 * @filter `datakit/dataview/fetch/options`
		 *
		 * @since  $ver$
		 *
		 * @param array $fetch_options The default options passed to the `fetch` calls.
		 */
		$fetch_options = (array) apply_filters( 'datakit/dataview/fetch/options', $fetch_options );

		wp_add_inline_script(
			'datakit/dataview',
			implode(
				"\n",
				[
					'let datakit_dataviews = {};',
					sprintf( 'const datakit_fetch_options = %s;', wp_json_encode( $fetch_options ) ?: '{}' ),
					sprintf(
						'const datakit_dataviews_rest_endpoint = "%s";',
						esc_attr( Router::get_url() ),
					),
				],
			),
			'before',
		);
	}

	/**
	 * Returns and maybe initializes the singleton plugin.
	 *
	 * @since $ver$
	 *
	 * @return self The plugin.
	 */
	public static function get_instance( DataViewRepository $repository ): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self( $repository );
		}

		return self::$instance;
	}
}
