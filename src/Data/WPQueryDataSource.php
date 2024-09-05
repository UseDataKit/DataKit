<?php

namespace DataKit\Plugin\Data;

use DataKit\DataViews\Data\BaseDataSource;
use DataKit\DataViews\Data\MutableDataSource;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use DataKit\DataViews\Data\Exception\ActionForbiddenException;

/**
 * A data source backed by WordPress' WP_Query.
 *
 * Provides an interface to retrieve and manipulate posts, pages, and custom post types.
 *
 * @since $ver$
 */
final class WPQueryDataSource extends BaseDataSource implements MutableDataSource {

	/**
	 * The base WP_Query instance that queries will use as a starting point (except for count queries).
	 *
	 * @var \WP_Query
	 * @since $ver$
	 */
	private \WP_Query $base_query;

	/**
	 * Stores the query arguments for later execution.
	 *
	 * @var array
	 * @since $ver$
	 */
	private array $query_args = [];

	/**
	 * Default query arguments.
	 *
	 * @var array
	 * @since $ver$
	 */
	private array $default_args = [
		'post_type'           => 'any',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'suppress_filters'    => false,
	];

	/**
	 * Constructor for WPQueryDataSource.
	 *
	 * @since $ver$
	 *
	 * @param \WP_Query|string|array|null $query Optional query parameter.
	 */
	public function __construct( $query = null ) {
		$this->base_query = new \WP_Query();
		$this->store_query_args( $query );
	}

	/**
	 * Stores the query arguments instead of immediately executing them.
	 *
	 * @param \WP_Query|string|array|null $query Optional query parameter.
	 */
	private function store_query_args( $query ): void {
		if ( $query instanceof \WP_Query ) {
			$this->query_args = $query->query_vars;
		} elseif ( is_string( $query ) || is_array( $query ) ) {
			$this->query_args = wp_parse_args( $query );
		}

		// Merge with default arguments.
		$this->query_args = wp_parse_args( $this->query_args, $this->default_args );

		// Ensure security-related arguments are properly set.
		$this->sanitize_query_args();
	}

	/**
	 * Sanitize and set security-related query arguments.
	 *
	 * @since $ver$
	 *
	 * @return void
	 */
	private function sanitize_query_args(): void {
		// Ensure 'suppress_filters' is always false for security.
		$this->query_args['suppress_filters'] = false;

		// Limit 'post_status' to viewable statuses if not explicitly set.
		if ( ! isset( $this->query_args['post_status'] ) ) {
			$this->query_args['post_status'] = array_values( get_post_stati( [ 'public' => true ] ) );
		}

		// Set 'post_type' to 'any' if not explicitly set.
		if ( ! isset( $this->query_args['post_type'] ) ) {
			$this->query_args['post_type'] = 'any';
		}

		// Ensure 'perm' is set to 'readable' if not explicitly set.
		if ( ! isset( $this->query_args['perm'] ) ) {
			$this->query_args['perm'] = 'readable';
		}

		// Remove any attempts to modify SQL directly.
		unset(
			$this->query_args['posts_where'],
			$this->query_args['posts_groupby'],
			$this->query_args['posts_join'],
			$this->query_args['posts_orderby']
		);
	}

	/**
	 * Run the query to fetch post IDs based on the limit, offset, and sorting criteria.
	 *
	 * @since $ver$
	 *
	 * @param int $limit The number of posts to retrieve.
	 * @param int $offset The number of posts to skip.
	 *
	 * @return string[] Array of post IDs.
	 */
	public function get_data_ids( int $limit = 100, int $offset = 0 ): array {
		$query_args = array_merge(
			$this->query_args,
			[
				'fields'         => 'ids',
				's'              => (string) $this->search,
				'posts_per_page' => $limit,
				'offset'         => $offset,
			],
			$this->get_sorting()
		);

		$this->base_query->query( $query_args );

		return array_map( 'strval', $this->base_query->posts );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function id(): string {
		return sprintf( 'wpquery-%s', wp_hash( wp_json_encode( $this->query_args ) ) );
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
	 *
	 * @return array
	 *
	 * @throws DataNotFoundException If the post does not exist.
	 * @throws ActionForbiddenException If the user doesn't have permission to read the post.
	 */
	public function get_data_by_id( string $id ): array {
		$post = get_post( (int) $id );

		if ( ! $post ) {
			throw DataNotFoundException::with_id( $this, $id );
		}

		// Check if the current user has permission to read this post.
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			throw new ActionForbiddenException(
				$this,
				// translators: %d is the post ID.
				sprintf( esc_html__( 'You do not have permission to read post #%d.', 'dk-datakit' ), $post->ID )
			);
		}

		return [
			'ID'                    => $post->ID,
			'post_author'           => $post->post_author,
			'post_date'             => $post->post_date,
			'post_date_gmt'         => $post->post_date_gmt,
			'post_content'          => $post->post_content,
			'post_title'            => $post->post_title,
			'post_excerpt'          => $post->post_excerpt,
			'post_status'           => $post->post_status,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_password'         => $post->post_password,
			'post_name'             => $post->post_name,
			'to_ping'               => $post->to_ping,
			'pinged'                => $post->pinged,
			'post_modified'         => $post->post_modified,
			'post_modified_gmt'     => $post->post_modified_gmt,
			'post_content_filtered' => $post->post_content_filtered,
			'post_parent'           => $post->post_parent,
			'guid'                  => $post->guid,
			'menu_order'            => $post->menu_order,
			'post_type'             => $post->post_type,
			'post_mime_type'        => $post->post_mime_type,
			'comment_count'         => $post->comment_count,
			'permalink'             => get_permalink( $post->ID ),
		];
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function count(): int {
		$query = new \WP_Query(
			array_merge(
				$this->query_args,
				[
					'fields'        => 'ids',
					'no_found_rows' => false,
					's'             => (string) $this->search,
				]
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function can_delete(): bool {
		return current_user_can( 'delete_posts' );
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 *
	 * @return void
	 *
	 * @throws DataNotFoundException If the post does not exist before deletion.
	 * @throws ActionForbiddenException If the user doesn't have permission to delete the post.
	 */
	public function delete_data_by_id( string ...$ids ): void {
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );

			if ( ! $post ) {
				throw DataNotFoundException::with_id( $this, $id );
			}

			if ( ! current_user_can( 'delete_post', $post->ID ) ) {
				throw new ActionForbiddenException(
					$this,
					// translators: %d is the post ID.
					sprintf( esc_html__( 'You do not have permission to delete post #%d.', 'dk-datakit' ), $post->ID )
				);
			}

			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since $ver$
	 */
	public function get_fields(): array {
		return [
			'ID'             => __( 'ID', 'dk-datakit' ),
			'post_author'    => __( 'Author', 'dk-datakit' ),
			'post_date'      => __( 'Date', 'dk-datakit' ),
			'post_content'   => __( 'Content', 'dk-datakit' ),
			'post_title'     => __( 'Title', 'dk-datakit' ),
			'post_excerpt'   => __( 'Excerpt', 'dk-datakit' ),
			'post_status'    => __( 'Status', 'dk-datakit' ),
			'comment_status' => __( 'Comment Status', 'dk-datakit' ),
			'ping_status'    => __( 'Ping Status', 'dk-datakit' ),
			'post_password'  => __( 'Password', 'dk-datakit' ),
			'post_name'      => __( 'Slug', 'dk-datakit' ),
			'post_modified'  => __( 'Modified Date', 'dk-datakit' ),
			'post_parent'    => __( 'Parent', 'dk-datakit' ),
			'guid'           => __( 'GUID', 'dk-datakit' ),
			'menu_order'     => __( 'Menu Order', 'dk-datakit' ),
			'post_type'      => __( 'Post Type', 'dk-datakit' ),
			'post_mime_type' => __( 'MIME Type', 'dk-datakit' ),
			'comment_count'  => __( 'Comment Count', 'dk-datakit' ),
			'permalink'      => __( 'Permalink', 'dk-datakit' ),
		];
	}
}
