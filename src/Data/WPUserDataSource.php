<?php

namespace DataKit\Plugin\Data;

use DataKit\DataViews\Data\BaseDataSource;
use DataKit\DataViews\Data\MutableDataSource;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use DataKit\DataViews\Data\Exception\ActionForbiddenException;

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
		if ( ! $query instanceof \WP_User_Query ) {
			$args = $query;
			// Prevent initial query on creation of the data source.
			$query = new \WP_User_Query();
			$query->prepare_query( $args );
		}

		$this->base_query = $query;
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
	 *
	 * @return array The updated query.
	 */
	private function merge_query_vars( array $additional_args = [] ): array {
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
			],
		);

		$query      = $this->merge_query_vars( $query );
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
			$flattened_meta,
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function count(): int {
		$query = $this->merge_query_vars(
			[
				'fields'      => 'ID',
				'count_total' => true,
				'search'      => '*' . (string) $this->search . '*',
			],
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
	public function can_delete(): bool {
		return current_user_can( 'delete_users' );
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 *
	 * @throws DataNotFoundException    If the user does not exist before deletion.
	 * @throws ActionForbiddenException If the current user tries to delete their own user.
	 */
	public function delete_data_by_id( string ...$ids ): void {
		// wp_delete_user() requires user.php, which isn't loaded inside a REST request.
		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( $ids as $id ) {
			if ( ! get_userdata( (int) $id ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw DataNotFoundException::with_id( $this, $id );
			}

			if ( get_current_user_id() === (int) $id ) {
				throw new ActionForbiddenException(
					$this, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					esc_html__( 'You cannot delete your own user.', 'datakit' ),
				);
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
		return [
			'ID'              => __( 'User ID', 'datakit' ),
			'user_login'      => __( 'User Login Name', 'datakit' ),
			'user_nicename'   => __( 'User Nice Name', 'datakit' ),
			'user_email'      => __( 'User Email', 'datakit' ),
			'user_url'        => __( 'User URL', 'datakit' ),
			'user_registered' => __( 'User Registration Date', 'datakit' ),
			'display_name'    => __( 'User Display Name', 'datakit' ),
			'nickname'        => __( 'User Nickname', 'datakit' ),
			'first_name'      => __( 'User First Name', 'datakit' ),
			'last_name'       => __( 'User Last Name', 'datakit' ),
			'description'     => __( 'User Description', 'datakit' ),
		];
	}
}
