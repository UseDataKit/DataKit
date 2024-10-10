<?php
/**
 * Ninja Forms Data Source
 *
 * @since $ver$
 * @package DataKit\Plugin\Data
 */

namespace DataKit\Plugin\Data;

use DataKit\DataViews\Data\BaseDataSource;
use DataKit\DataViews\Data\MutableDataSource;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use NinjaForms\Includes\Factories\SubmissionFilterFactory;
use NinjaForms\Includes\Factories\SubmissionAggregateFactory;

/**
 * Data source backed by a Ninja Forms form.
 *
 * @since $ver$
 */
class NinjaFormsDataSource extends BaseDataSource implements MutableDataSource {

	/**
	 * The form ID.
	 *
	 * @var int
	 * @since $ver$
	 */
	private int $form_id;

	/**
	 * The form object.
	 *
	 * @var \NF_Abstracts_ModelFactory
	 * @since $ver$
	 */
	private $form;

	/**
	 * Microcache for the "current" entries.
	 *
	 * @var array
	 * @since $ver$
	 */
	private array $entries;

	/**
	 * Microcache for the data source fields.
	 *
	 * @var array
	 * @since $ver$
	 */
	private array $fields;

	/**
	 * Constructor.
	 *
	 * @since $ver$
	 *
	 * @param int $form_id The form ID.
	 *
	 * @throws DataNotFoundException When the data source could not be instantiated.
	 */
	public function __construct( int $form_id ) {

		if ( ! class_exists( 'Ninja_Forms' ) ) {
			throw new DataNotFoundException( esc_html__( 'Data source cannot be used, as Ninja Forms is not available.', 'dk-datakit' ) );
		}

		$this->form = \Ninja_Forms()->form( $form_id )->get();

		if ( ! $this->form ) {
			throw new DataNotFoundException( sprintf( esc_html__( 'Ninja Forms data source (%d) not found', 'dk-datakit' ), $form_id ) );
		}

		$this->form_id = $form_id;
	}

	/**
	 * Get the unique identifier for this data source.
	 *
	 * @since $ver$
	 *
	 * @return string The unique identifier.
	 */
	public function id(): string {
		return sprintf( 'ninja-forms-%d', $this->form_id );
	}

	/**
	 * Get data IDs based on the current query.
	 *
	 * @since $ver$
	 *
	 * @todo Implement searching and sorting.
	 *
	 * @param int $limit The number of items to return.
	 * @param int $offset The number of items to skip.
	 *
	 * @return array An array of data IDs.
	 */
	public function get_data_ids( int $limit = 100, int $offset = 0 ): array {

		if ( ! $this->form ) {
			return [];
		}

		$current_page = floor( $offset / $limit ) + 1;

		// Initialize the meta query with the existing condition
		$meta_query = [
			[
				'key'     => '_form_id',
				'value'   => $this->form_id,
				'compare' => '=', // Optional: specify the comparison operator
			],
			// TODO: Implement search and sorting.
		];

		// Initialize the main query arguments
		$args = [
			'post_type'      => 'nf_sub',
			'posts_per_page' => $limit,
			'paged'          => $current_page,
			'post_status'    => [ 'active', 'publish' ], // Array of post statuses
			'meta_query'     => $meta_query,
		];

		// Execute the query
		$subs = get_posts( $args );

		return wp_list_pluck( $subs, 'ID' );
	}

	/**
	 * Get data by ID.
	 *
	 * @since $ver$
	 *
	 * @param string $id The ID of the data to retrieve.
	 *
	 * @return array The data associated with the given ID.
	 * @throws DataNotFoundException If the data is not found.
	 */
	public function get_data_by_id( string $id ): array {
		$submission = $this->entries[ $id ] ?? $this->get_single_submission( $id );

		if ( ! $submission ) {
			throw DataNotFoundException::with_id( $this, $id );
		}

		return $this->format_submission( $submission );
	}

	/**
	 * Format a submission for output.
	 *
	 * @since $ver$
	 *
	 * @param \NF_Database_Models_Submission $submission The submission to format.
	 *
	 * @return array The formatted submission data.
	 */
	private function format_submission( \NF_Database_Models_Submission $submission ): array {
		$formatted = [
			'id'             => $submission->get_seq_num(),
			'date_submitted' => $submission->get_sub_date(),
			'status'         => $submission->get_status(),
		];

		$field_values = $submission->get_field_values();
		foreach ( $field_values as $field_id => $value ) {
			$formatted[ $field_id ] = $value;
		}

		return $formatted;
	}

	/**
	 * Get the total count of items in the data source.
	 *
	 * @since $ver$
	 *
	 * @todo Implement filters and search.
	 *
	 * @return int The total count of items.
	 */
	public function count(): int {
		$params = [];

		return sizeof( \Ninja_Forms()->form( $this->form_id )->get_subs( $params ) );
	}

	/**
	 * Check if the current user can delete items.
	 *
	 * @since $ver$
	 *
	 * @return bool Whether the current user can delete items.
	 */
	public function can_delete(): bool {
		return current_user_can( 'delete_posts' ); // TODO: Is there a better capability to check?
	}

	/**
	 * Delete data by ID.
	 *
	 * @since $ver$
	 *
	 * @param string ...$ids The IDs of the data to delete.
	 */
	public function delete_data_by_id( string ...$ids ): void {
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'delete_post', $id ) ) {
				continue;
			}

			$sub = \Ninja_Forms()->form( $this->form_id )->get_sub( $id );
			if ( $sub ) {
				$sub->delete();
			}
		}
	}

	/**
	 * Get the fields available in this data source.
	 *
	 * @since $ver$
	 *
	 * @return array An array of available fields.
	 */
	public function get_fields(): array {
		if ( ! empty( $this->fields ) ) {
			return $this->fields;
		}

		$form_fields = \Ninja_Forms()->form( $this->form_id )->get_fields();

		$fields = [
			'id'             => __( 'ID', 'dk-datakit' ),
			'date_submitted' => __( 'Date Submitted', 'dk-datakit' ),
			'status'         => __( 'Status', 'dk-datakit' ),
		];

		foreach ( $form_fields as $field ) {
			$fields[ $field->get_id() ] = $field->get_setting( 'label' );
		}

		$this->fields = $fields;

		return $this->fields;
	}

	/**
	 * Get a single submission by ID.
	 *
	 * @since $ver$
	 *
	 * @param string $id The ID of the submission to retrieve.
	 *
	 * @return \NF_Database_Models_Submission|null The submission object, or null if not found.
	 */
	private function get_single_submission( string $id ): ?\NF_Database_Models_Submission {
		return \Ninja_Forms()->form( $this->form_id )->get_sub( $id );
	}
}
