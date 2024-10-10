<?php

namespace DataKit\Plugin\Data;

use Closure;
use DataKit\DataViews\Data\BaseDataSource;
use DataKit\DataViews\Data\MutableDataSource;
use DataKit\DataViews\Data\Exception\DataSourceNotFoundException;
use DataKit\DataViews\DataView\Operator;
use WPForms_Form_Handler;

/**
 * Data source backed by a WPForms form (Premium version required).
 *
 * @since $ver$
 */
final class WPFormsDataSource extends BaseDataSource implements MutableDataSource {
	/**
	 * Fields that are top-level search keys.
	 *
	 * @since $ver$
	 *
	 * @var string[]
	 */
	private static array $top_level_filters = [ 'date', 'entry_id' ];

	/**
	 * The form ID.
	 *
	 * @since $ver$
	 *
	 * @var int
	 */
	private int $form_id;

	/**
	 * Microcache for the "current" entries.
	 *
	 * @since $ver$
	 *
	 * @var array[]
	 */
	private array $entries = [];

	/**
	 * Microcache for the data source fields.
	 *
	 * @since $ver$
	 *
	 * @var array<string, string>
	 */
	private array $fields = [];

	/**
	 * Creates the data source.
	 *
	 * @since $ver$
	 *
	 * @param int $form_id The form ID.
	 * @throws DataSourceNotFoundException When the data source could not be instantiated.
	 */
	public function __construct( int $form_id ) {
		if ( ! function_exists( 'wpforms' ) || ! wpforms()->is_pro() ) {
			throw new DataSourceNotFoundException( esc_html__( 'Data source cannot be used, as WPForms Premium is not available.', 'dk-datakit' ) );
		}

		$form = wpforms()->form->get( $form_id );
		if ( empty( $form ) ) {
			// translators: %d is the form ID.
			throw new DataSourceNotFoundException( sprintf( esc_html__( 'WPForms data source (%d) not found', 'dk-datakit' ), $form_id ) );
		}

		$this->form_id = $form_id;
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function id(): string {
		return sprintf( 'wpforms-%d', $this->form_id );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function can_delete(): bool {
		return wpforms_current_user_can( 'delete_entries_form_single', $this->form_id );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function delete_data_by_id( string ...$ids ): void {
		foreach ( $ids as $id ) {
			wpforms()->entry->delete( (int) $id );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_ids( int $limit = 100, int $offset = 0 ): array {
		$args = [
			'form_id' => $this->form_id,
			'number'  => $limit,
			'offset'  => $offset,
		];

		$args = array_merge( $args, $this->get_search_criteria() );

		$sorting = $this->get_sorting();
		if ( ! empty( $sorting ) ) {
			$args['orderby'] = $sorting['orderby'];
			$args['order']   = $sorting['order'];
		}

		$entries = wpforms()->entry->get_entries( $args );

		$this->entries = [];
		$ids           = [];
		foreach ( $entries as $entry ) {
			$id                   = $entry->entry_id;
			$this->entries[ $id ] = $entry;
			$ids[]                = (string) $id;
		}

		return $ids;
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_by_id( string $id ): array {
		$entry = $this->entries[ $id ] ?? wpforms()->entry->get( (int) $id );

		if ( empty( $entry ) ) {
			return [];
		}

		$fields          = wpforms()->entry_fields->get_fields( [ 'entry_id' => $entry->entry_id ] );
		$processed_entry = [
			'entry_id' => $entry->entry_id,
			'form_id'  => $entry->form_id,
			'date'     => $entry->date,
			'status'   => $entry->status,
		];

		foreach ( $fields as $field ) {
			$processed_entry[ $field->field_id ] = $field->value;
		}

		return $processed_entry;
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function count(): int {
		$args = [
			'form_id' => $this->form_id,
		];

		$args = array_merge( $args, $this->get_search_criteria() );

		return wpforms()->entry->get_entries( $args, true );
	}

	/**
	 * Returns the search criteria based on the filters.
	 *
	 * @since $ver$
	 *
	 * @return array The search criteria.
	 */
	private function get_search_criteria(): array {
		$filters = [];

		if ( $this->filters ) {
			foreach ( $this->filters->to_array() as $filter ) {
				if ( in_array( $filter['field'], self::$top_level_filters, true ) ) {
					$filters[ $filter['field'] ] = $filter['value'];
				} else {
					$filters['field_id'][]   = $filter['field'];
					$filters['value'][]      = $filter['value'];
					$filters['comparison'][] = $this->map_operator( $filter['operator'] );
				}
			}
		}

		if ( $this->search && ! $this->search->is_empty() ) {
			$filters['search'] = [ 'value' => (string) $this->search ];
		}

		return $filters;
	}

	/**
	 * Maps the field operator to a WPForms search operator.
	 *
	 * @since $ver$
	 *
	 * @param string $operator The field operator.
	 *
	 * @return string The WPForms search operator.
	 */
	private function map_operator( string $operator ): string {
		$case = Operator::try_from( $operator );

		$lookup = [
			(string) Operator::is()       => '=',
			(string) Operator::isNot()    => '!=',
			(string) Operator::isAny()    => 'LIKE',
			(string) Operator::isAll()    => '=',
			(string) Operator::isNotAll() => 'NOT LIKE',
			(string) Operator::isNone()   => 'NOT LIKE',
		];

		return $lookup[ (string) $case ] ?? 'LIKE';
	}

	/**
	 * Returns the sorting for WPForms based on the sort object.
	 *
	 * @since $ver$
	 *
	 * @return array The WPForms sorting.
	 */
	private function get_sorting(): array {
		if ( ! $this->sort ) {
			return [];
		}

		$sort = $this->sort->to_array();

		return [
			'orderby' => $sort['field'],
			'order'   => strtoupper( $sort['direction'] ),
		];
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_fields(): array {
		if ( ! empty( $this->fields ) ) {
			return $this->fields;
		}

		$form_handler = new WPForms_Form_Handler();
		$form         = $form_handler->get( $this->form_id );
		$output       = [
			'entry_id' => 'Entry ID',
			'form_id'  => 'Form ID',
			'date'     => 'Date',
			'status'   => 'Status',
		];

		if ( ! empty( $form->post_content['fields'] ) ) {
			foreach ( $form->post_content['fields'] as $field ) {
				$output[ $field['id'] ] = $field['label'];

				if ( ! empty( $field['choices'] ) ) {
					foreach ( $field['choices'] as $choice_id => $choice ) {
						$output[ $field['id'] . '_' . $choice_id ] = $choice['label'];
					}
				}
			}
		}

		$this->fields = $output;

		return $output;
	}
}
