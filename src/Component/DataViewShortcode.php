<?php

namespace DataKit\Plugin\Component;

use DataKit\DataViews\AccessControl\AccessController;
use DataKit\DataViews\AccessControl\Capability;
use DataKit\DataViews\DataView\DataViewRepository;
use DataKit\DataViews\DataViewException;
use DataKit\Plugin\Rest\Router;

/**
 * Responsible for registering and rendering shortcodes.
 *
 * @since $ver$
 */
final class DataViewShortcode {
	/**
	 * The name of the shortcode.
	 *
	 * @since $ver$
	 *
	 * @var string
	 */
	private const SHORTCODE = 'dataview';

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
	 * Runtime cache for which DataViews are rendered.
	 *
	 * @since $ver$
	 *
	 * @var string[]
	 */
	private array $rendered = [];

	/**
	 * The Access Controller.
	 *
	 * @since $ver$
	 *
	 * @var AccessController
	 */
	private AccessController $access_controller;

	/**
	 * Creates the shortcode instance.
	 *
	 * @since $ver$
	 */
	private function __construct( DataViewRepository $data_view_repository, AccessController $access_controller ) {
		$this->data_view_repository = $data_view_repository;
		$this->access_controller    = $access_controller;

		add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @since $ver$
	 *
	 * @param array<int|string, mixed> $attributes The shortcode attributes.
	 *
	 * @return string The shortcode output.
	 * @todo  Add search & sorting attributes.
	 */
	public function render_shortcode( array $attributes ): string {
		$id = $attributes['id'] ?? null;

		if (
			! $id
			|| ! $this->data_view_repository->has( $id )
		) {
			return '';
		}

		// Only add data set once per ID.
		if ( ! in_array( $id, $this->rendered, true ) ) {
			try {
				$dataview = $this->data_view_repository->get( $id );
				if ( ! $this->access_controller->can( new Capability\ViewDataView( $dataview ) ) ) {
					return '';
				}

				wp_enqueue_script( 'datakit/dataview' );
				wp_enqueue_style( 'datakit/dataview' );

				$js_safe = sprintf( 'datakit_dataviews["%s"] = %s;', esc_attr( $id ), $dataview->to_js() );
				$js_safe = str_replace( '{REST_ENDPOINT}', Router::get_url(), $js_safe );
			} catch ( DataViewException $e ) {
				return '';
			}

			if (
				wp_is_block_theme()
				&& ! wp_script_is( 'datakit/dataview', 'registered' )
			) {
				add_action(
					'wp_enqueue_scripts',
					function () use ( $js_safe ) {
						wp_add_inline_script( 'datakit/dataview', $js_safe, 'before' );
					},
				);
			}

			wp_add_inline_script( 'datakit/dataview', $js_safe, 'before' );

			$this->rendered[] = $id;
		}

		return sprintf( '<div data-dataview="%s"></div>', $id );
	}

	/**
	 * Returns and maybe initializes the singleton.
	 *
	 * @since $ver$
	 *
	 * @return self The singleton.
	 */
	public static function get_instance(
		DataViewRepository $data_view_repository,
		AccessController $access_controller
	): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self( $data_view_repository, $access_controller );
		}

		return self::$instance;
	}
}
