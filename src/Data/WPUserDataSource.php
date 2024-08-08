<?php

namespace DataKit\Plugin\Data;

use DataKit\DataViews\Data\BaseDataSource;
use DataKit\DataViews\Data\MutableDataSource;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use DataKit\DataViews\DataView\Filters;
use DataKit\DataViews\DataView\Sort;
use DataKit\DataViews\Field\Field;

/**
 * A data source backed by WordPress users.
 *
 * @since $ver$
 */
final class WPUserDataSource extends BaseDataSource implements MutableDataSource {
	/**
	 * The unique ID for the data source.
	 *
	 * @since $ver$
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Creates the data source.
	 *
	 * @since $ver$
	 *
	 * @param string $id The data source identifier.
	 * @param \WP_User_Query|array|null $query The WP_User_Query instance or an array of query arguments or null.
	 */
	public function __construct( string $id, $query = null ) {
		$this->id = $id;

		if ( $query instanceof \WP_User_Query ) {
			$this->user_query = $query;
		} else {
			$this->user_query = new \WP_User_Query( $query );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_ids( int $limit = 100, int $offset = 0 ): array {
		$query = array_merge(
			$this->get_search_criteria(),
			$this->get_sorting(),
			[
				'number' => $limit,
				'offset' => $offset,
				'fields' => 'ID',
			]
		);
		$user_query = new \WP_User_Query( $query );
		$results = $user_query->get_results();

		return $results ? array_map( 'strval', $results ) : [];
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_by_id( string $id ): array {
		$user = get_userdata( $id );

		if ( ! $user ) {
			throw DataNotFoundException::with_id( $this, $id );
		}

		// Get all user meta data
		$user_meta = get_user_meta( $id );

		// Flatten user meta
		$flattened_meta = [];
		foreach ( $user_meta as $key => $value ) {
			$flattened_meta[ $key ] = maybe_unserialize( $value[0] );
		}

		// Combine user data with user meta
		return array_merge(
			[
				'id'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => $user->user_registered,
				'display_name'    => $user->display_name,
				'user_status'     => $user->user_status,
			],
			$flattened_meta
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function count(): int {
		$query = array_merge(
			$this->get_search_criteria(),
			[
				'fields'      => 'ID',
				'count_total' => true,
			]
		);
		$user_query = new \WP_User_Query( $query );
		return $user_query->get_total();
	}

	/**
	 * Returns the search criteria based on the filters.
	 *
	 * @since $ver$
	 *
	 * @return array The search criteria.
	 */
	private function get_search_criteria(): array {
		if ( ! $this->filters && ( ! $this->search || $this->search->is_empty() ) ) {
			return [];
		}

		$criteria = [];

		if ( $this->filters ) {
			$criteria = array_merge(
				$criteria,
				array_reduce(
					$this->filters->to_array(),
					function ( $carry, $filter ) {
						$carry[$filter['field']] = $filter['value'];
						return $carry;
					},
					[]
				)
			);
		}

		if ( $this->search ) {
			$criteria['search'] = '*' . $this->search . '*';
		}

		return $criteria;
	}

	/**
	 * Returns the sorting criteria based on the sort object.
	 *
	 * @since $ver$
	 *
	 * @return array The sorting criteria.
	 */
	private function get_sorting(): array {
		if ( ! $this->sort ) {
			return [];
		}

		$sort = $this->sort->to_array();

		// TODO: The UUID separator isn't getting removed by the Sort ("user_email--DK--a62a82477ba187a2a4aacf09b9a0d145").
		$field = explode( Field::UUID_GLUE, $sort['field'] );

		return [
			'orderby'  => $field[0],
			'order'    => strtoupper( $sort['direction'] ),
		];
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function can_delete(): bool {
		return current_user_can( 'delete_users' );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function delete_data_by_id( string ...$ids ): void {
		foreach ( $ids as $id ) {
			if ( ! get_userdata( $id ) ) {
				throw DataNotFoundException::with_id( $this, $id );
			}

			wp_delete_user( $id );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_fields(): array {
		$fields = [
			'id'              => __( 'ID', 'dk-datakit' ),
			'user_login'      => __( 'User Login', 'dk-datakit' ),
			'user_email'      => __( 'User Email', 'dk-datakit' ),
			'user_registered' => __( 'User Registered', 'dk-datakit' ),
			'display_name'    => __( 'Display Name', 'dk-datakit' ),
			'user_status'     => __( 'User Status', 'dk-datakit' ),
		];

		// Add all registered user meta keys
		global $wpdb;
		$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM $wpdb->usermeta" );
		foreach ( $meta_keys as $meta_key ) {
			$fields[ $meta_key ] = ucfirst( str_replace( '_', ' ', $meta_key ) );
		}

		return $fields;
	}
}
