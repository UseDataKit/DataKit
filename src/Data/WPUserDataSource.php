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
	 * The base WP_User_Query instance that all queries will use as a starting point.
	 *
	 * Example: new \WP_User_Query( [ 'role' => 'editor' ] ) would be the base, and then searches will be performed
	 * on top of that.
	 *
	 * @since $ver$
	 *
	 * @var \WP_User_Query
	 */
	private \WP_User_Query $base_query;

	/**
	 * Creates the data source.
	 *
	 * @since $ver$
	 *
	 * @param \WP_User_Query|array|string|null $query The WP_User_Query instance or an array of query arguments or null.
	 */
	public function __construct( $query = null ) {
		if ( $query instanceof \WP_User_Query ) {
			$this->base_query = $query;
		} else {
			$this->base_query = new \WP_User_Query( $query );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function id(): string {
		return sprintf( 'wpuser-%s', wp_hash( wp_json_encode( $this->base_query->query_vars ) ) );
	}

	/**
	 * Merges the base query with additional query arguments.
	 *
	 * @since $ver$
	 *
	 * @param array $additional_args Additional query arguments.
	 */
	public function merge_query( array $additional_args = [] ): array {
		return array_merge( $this->base_query->query_vars, $additional_args );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_ids( int $limit = 100, int $offset = 0 ): array {
		$query = array_merge(
			$this->get_sorting(),
			[
				'number' => $limit,
				'offset' => $offset,
				'fields' => 'ID',
				'search' => '*' . (string) $this->search . '*',
			]
		);

		$query = $this->merge_query( $query );

		$user_query = new \WP_User_Query( $query );
		$results    = $user_query->get_results();

		return $results ? array_map( 'strval', $results ) : [];
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_data_by_id( string $id ): array {
		$user = get_userdata( (int) $id );

		if ( ! $user ) {
			throw DataNotFoundException::with_id( $this, $id );
		}

		// Get all user meta data.
		$user_meta = get_user_meta( (int) $id );

		// Flatten user meta.
		$flattened_meta = [];
		foreach ( $user_meta as $key => $value ) {
			$flattened_meta[ $key ] = maybe_unserialize( $value[0] );
		}

		// Combine user data with user meta.
		return array_merge(
			[
				'display_name'    => $user->display_name,
				'id'              => $user->ID,
				'user_email'      => $user->user_email,
				'user_login'      => $user->user_login,
				'user_nicename'   => $user->user_nicename,
				'user_registered' => $user->user_registered,
				'user_status'     => $user->user_status,
				'user_url'        => $user->user_url,
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
		$query = $this->merge_query(
			[
				'fields'      => 'ID',
				'count_total' => true,
				'search'      => '*' . (string) $this->search . '*',
			]
		);

		$user_query = new \WP_User_Query( $query );

		return $user_query->get_total();
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
			'orderby' => $field[0],
			'order'   => strtoupper( $sort['direction'] ),
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
		// wp_delete_user() requires user.php, which isn't loaded inside a REST request.
		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( $ids as $id ) {
			if ( ! get_userdata( (int) $id ) ) {
				throw DataNotFoundException::with_id( $this, $id );
			}

			wp_delete_user( (int) $id );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_fields(): array {
		$fields = [
			'display_name'    => __( 'Display Name', 'dk-datakit' ),
			'id'              => __( 'ID', 'dk-datakit' ),
			'user_email'      => __( 'User Email', 'dk-datakit' ),
			'user_login'      => __( 'User Login', 'dk-datakit' ),
			'user_nicename'   => __( 'User Nicename', 'dk-datakit' ),
			'user_registered' => __( 'User Registered', 'dk-datakit' ),
			'user_status'     => __( 'User Status', 'dk-datakit' ),
			'user_url'        => __( 'User URL', 'dk-datakit' ),
		];

		// Add all registered user meta keys.
		global $wpdb;
		$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM $wpdb->usermeta" );
		foreach ( $meta_keys as $meta_key ) {
			$fields[ $meta_key ] = ucfirst( str_replace( '_', ' ', $meta_key ) );
		}

		return $fields;
	}
}
