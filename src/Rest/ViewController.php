<?php

namespace DataKit\Plugin\Rest;

use DataKit\DataViews\AccessControl\AccessController;
use DataKit\DataViews\AccessControl\Capability;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use DataKit\DataViews\DataView\DataItem;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewNotFoundException;
use DataKit\DataViews\DataView\DataViewRepository;
use DataKit\DataViews\DataView\Filters;
use DataKit\DataViews\DataView\Pagination;
use DataKit\DataViews\DataView\Search;
use DataKit\DataViews\DataView\Sort;
use DataKit\DataViews\Translation\Translatable;
use DataKit\Plugin\Translation\WordPressTranslator;
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
	 * The Access Controller.
	 *
	 * @since $ver$
	 *
	 * @var AccessController
	 */
	private AccessController $access_controller;

	/**
	 * The translator.
	 *
	 * @since $ver$
	 *
	 * @var WordPressTranslator
	 */
	private WordPressTranslator $translator;

	/**
	 * Creates the controller.
	 *
	 * @since $ver$
	 *
	 * @param DataViewRepository $dataview_repository The DataView repository.
	 */
	public function __construct(
		DataViewRepository $dataview_repository,
		AccessController $access_controller,
		WordPressTranslator $translator
	) {
		$this->dataview_repository = $dataview_repository;
		$this->access_controller   = $access_controller;
		$this->translator          = $translator;
	}

	/**
	 * Whether the current user can view the result.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool Whether the current user can view the result.
	 */
	public function can_view( WP_REST_Request $request ): bool {
		$view_id = (string) ( $request->get_param( 'view_id' ) ?? '' );
		if ( ! $this->dataview_repository->has( $view_id ) ) {
			return false;
		}

		try {
			$dataview = $this->dataview_repository->get( $view_id );

			return $this->access_controller->can( new Capability\ViewDataView( $dataview ) );
		} catch ( DataViewNotFoundException $e ) {
			return false;
		}
	}

	/**
	 * Returns the DataView data.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array{data: array<string, mixed>, paginationInfo: array<string, int>}|WP_Error The data or error object.
	 */
	public function get_view( WP_REST_Request $request ) {
		try {
			$data_view = $this->dataview_repository->get( $request->get_param( 'view_id' ) );
			$params    = $request->get_params();

			// Update view with provided params.
			$data_source = $data_view->data_source()
				->filter_by( Filters::from_array( $params['filters'] ?? [] ) )
				->search_by( Search::from_string( $params['search'] ?? '' ) );

			$pagination = ( $params['page'] ?? null ) ? Pagination::from_array( $params ) : Pagination::default();

			if ( $params['sort'] ?? [] ) {
				$data_source = $data_source->sort_by( Sort::from_array( $params['sort'] ) );
			}

			return [
				'data'           => $data_view->get_data( $data_source, $pagination ),
				'paginationInfo' => $pagination->info( $data_source ),
			];
		} catch ( \Exception $e ) {
			$message = $e instanceof Translatable ? $e->translate( $this->translator ) : $e->getMessage();

			return new WP_Error( 'datakit_dataview_get_view', $message );
		}
	}

	/**
	 * Returns the result for a single item.
	 *
	 * @since $ver$
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array{dataview_id: string, data_id: string, html: string}|WP_Error The response.
	 */
	public function get_data_item( WP_REST_Request $request ) {
		$view_id = (string) ( $request->get_param( 'view_id' ) ?? '' );
		$data_id = (string) ( $request->get_param( 'data_id' ) ?? '' );

		try {
			$dataview  = $this->dataview_repository->get( $view_id );
			$data_item = $dataview->get_view_data_item( $data_id );
		} catch ( \Exception $e ) {
			$data = [ 'exception' => $e ];
			if ( $e instanceof DataNotFoundException ) {
				$data['status'] = 404;
			}

			$message = $e instanceof Translatable ? $e->translate( $this->translator ) : $e->getMessage();

			return new WP_Error( 'datakit_dataview_get_item', $message, $data );
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
		/** @phpstan-ignore closure.unusedUse, closure.unusedUse */
		( static function () use ( $template, $dataview, $data_item ) {
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
