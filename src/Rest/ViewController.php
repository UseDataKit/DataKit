<?php

namespace DataKit\Plugin\Rest;

use DataKit\DataViews\DataView\DataItem;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewRepository;
use DataKit\DataViews\DataView\Filters;
use DataKit\DataViews\DataView\Pagination;
use DataKit\DataViews\DataView\Sort;
use WP_Error;
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
	 * Returns the DataView data.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array|WP_Error The data or error object.
	 */
	public function get_view( WP_REST_Request $request ) {
		try {
			$data_view = $this->dataview_repository->get( $request->get_param( 'id' ) );
			$params    = $request->get_params();

			// Update view with provided params.
			$data_source = $data_view->data_source()
				->filter_by( Filters::from_array( $params['filters'] ?? [] ) )
				->search_by( $params['search'] ?? '' );

			$pagination = ( $params['page'] ?? null ) ? Pagination::from_array( $params ) : Pagination::default();

			if ( $params['sort'] ?? [] ) {
				$data_source = $data_source->sort_by( Sort::from_array( $params['sort'] ) );
			}

			return [
				'data'           => $data_view->get_data( $data_source, $pagination ),
				'paginationInfo' => $pagination->info( $data_source ),
			];
		} catch ( \Exception $e ) {
			return new WP_Error( 'datakit_dataview_get_view', $e->getMessage() );
		}
	}

	/**
	 * Returns the result for a single item.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array|WP_Error The response.
	 */
	public function get_data_item( WP_REST_Request $request ) {
		$view_id = (string) ( $request->get_param( 'view_id' ) ?? '' );
		$data_id = (string) ( $request->get_param( 'data_id' ) ?? '' );

		try {
			$dataview  = $this->dataview_repository->get( $view_id );
			$data_item = $dataview->get_view_data_item( $data_id );
		} catch ( \Exception $e ) {
			return new WP_Error( 'datakit_dataview_get_item', $e->getMessage(), [ 'exception' => $e ] );
		}

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
