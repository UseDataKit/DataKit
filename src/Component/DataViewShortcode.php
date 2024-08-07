<?php

namespace DataKit\Plugin\Component;

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
	 * @var array
	 */
	private array $rendered = [];

	/**
	 * Creates the shortcode instance.
	 *
	 * @since $ver$
	 */
	private function __construct( DataViewRepository $data_view_repository ) {
		$this->data_view_repository = $data_view_repository;

		add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @since $ver$
	 *
	 * @param array $attributes The shortcode attributes.
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
			wp_enqueue_script( 'datakit/dataview' );
			wp_enqueue_style( 'datakit/dataview' );

			$dataview = $this->data_view_repository->get( $id );

			try {
				$js = sprintf( 'datakit_dataviews["%s"] = %s;', esc_attr( $id ), $dataview->to_js() );
				$js = str_replace( '{REST_ENDPOINT}', Router::get_url(), $js );
			} catch ( DataViewException $e ) {
				return '';
			}

			if (
				wp_is_block_theme()
				&& ! wp_script_is( 'datakit/dataview', 'registered' )
			) {
				add_action(
					'wp_enqueue_scripts',
					function () use ( $js ) {
						wp_add_inline_script( 'datakit/dataview', $js, 'before' );
					},
				);
			}
			wp_add_inline_script( 'datakit/dataview', $js, 'before' );

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
	public static function get_instance( DataViewRepository $data_view_repository ): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self( $data_view_repository );
		}

		return self::$instance;
	}
}
