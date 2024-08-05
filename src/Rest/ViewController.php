<?php

namespace DataKit\Plugin\Rest;

use DataKit\DataViews\DataView\DataItem;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewRepository;
use WP_REST_Request;

/**
 * Controller responsible for a single view result.
 *
 * @since $ver$
 */
final class ViewController {
	/**
	 * The DataView repository.
	 *
	 * @since $ver$
	 *
	 * @var DataViewRepository
	 */
	private DataViewRepository $dataview_repository;

	/**
	 * Creates the controller.
	 *
	 * @since $ver$
	 *
	 * @param DataViewRepository $dataview_repository The DataView repository.
	 */
	public function __construct( DataViewRepository $dataview_repository ) {
		$this->dataview_repository = $dataview_repository;
	}

	/**
	 * Whether the current user can view the result.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool Whether the current user can view the result.
	 * @todo  Add security from DataView.
	 */
	public function can_view( WP_REST_Request $request ): bool {
		$view_id = (string) ( $request->get_param( 'view_id' ) ?? '' );

		return true;
	}

	/**
	 * Returns the result for a single item.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array The response.
	 */
	public function get_item( WP_REST_Request $request ): array {
		$view_id = (string) ( $request->get_param( 'view_id' ) ?? '' );
		$data_id = (string) ( $request->get_param( 'data_id' ) ?? '' );

		$dataview  = $this->dataview_repository->get( $view_id );
		$data_item = $dataview->get_view_data_item( $data_id );

		ob_start();

		/**
		 * Overwrites the default template used for a single date item view.
		 *
		 * @filter `datakit/dataview/view/template`
		 *
		 * @since  $ver$
		 *
		 * @param string   $template  The absolute path of the template to render.
		 * @param DataView $dataview  The DataView.
		 * @param DataItem $data_item The data item to render.
		 */
		$template = (string) apply_filters(
			'datakit/dataview/view/template',
			dirname( __DIR__, 2 ) . '/vendor/datakit/sdk/templates/view/table.php',
			$dataview,
			$data_item,
		);

		// Scope rendering of template to avoid class leaking.
		/* @phpstan-ignore closure.unusedUse */
		( static function () use ( $template, $data_item ) {
			require $template;
		} )();

		$html = ob_get_clean();

		return [
			'dataview_id' => $dataview->id(),
			'data_id'     => $data_id,
			'html'        => $html,
		];
	}
}
