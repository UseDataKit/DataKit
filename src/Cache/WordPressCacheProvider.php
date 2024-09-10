<?php

namespace DataKit\Plugin\Cache;

use DataKit\DataViews\Cache\BaseCacheProvider;
use DataKit\DataViews\Cache\CacheItem;
use DateInterval;
use Exception;

/**
 * A cache provider backed by WordPress' Option API.
 *
 * @since $ver$
 */
final class WordPressCacheProvider extends BaseCacheProvider {
	/**
	 * A prefix that indicates the cache item is for this cache provider.
	 *
	 * @since $ver$
	 */
	private const CACHE_KEY_PREFIX = 'DATAKIT_ITEM';

	/**
	 * A prefix that indicates the cache item is a tag.
	 *
	 * @since $ver$
	 */
	private const TAG_PREFIX = 'DATAKIT_TAG';

	/**
	 * Micro cache of cache items.
	 *
	 * @since $ver$
	 *
	 * @var array<string, CacheItem>
	 */
	private array $items = [];

	/**
	 * Returns a normalized cache key.
	 *
	 * @since $ver$
	 *
	 * @param string $key The original key.
	 *
	 * @return string THe normalized key.
	 */
	private function normalize_key( string $key, string $type = 'item' ): string {
		return sprintf( '%s_%s', 'item' === $type ? self::CACHE_KEY_PREFIX : self::TAG_PREFIX, $key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function set( string $key, $value, ?int $ttl = null, array $tags = [] ): void {
		try {
			$time = (int) $ttl > 0
				? ( $this->clock->now()->add( new DateInterval( 'PT' . $ttl . 'S' ) ) )
				: null;
		} catch ( Exception $e ) {
			throw new \InvalidArgumentException( $e->getMessage(), $e->getCode(), $e );
		}

		update_option( $this->normalize_key( $key ), new CacheItem( $key, $value, $time, $tags ), false );

		$this->add_tags( $key, $tags );
	}

	/**
	 * Records a key for all provided tags.
	 *
	 * @since $ver$
	 *
	 * @param string $key  The key to tag.
	 * @param array  $tags The tags.
	 */
	private function add_tags( string $key, array $tags ): void {
		foreach ( $tags as $tag ) {
			if ( ! is_string( $tag ) ) {
				throw new \InvalidArgumentException( 'A tag must be a string.' );
			}

			$tag_key = $this->normalize_key( $tag, 'tag' );
			$tags    = (array) get_option( $tag_key, [] );
			$tags    = array_unique( array_merge( $tags, [ $key ] ) );

			update_option( $tag_key, $tags, false );
		}
	}

	/**
	 * {@inheritDoc}
     *
	 * @since $ver$
	 */
	protected function doGet( string $key ): ?CacheItem {
		if ( ! isset( $this->items[ $key ] ) ) {
			$normalized_key      = $this->normalize_key( $key );
			$this->items[ $key ] = get_option( $normalized_key );
		}

		$item = $this->items[ $key ] ?? null;

		return $item instanceof CacheItem ? $item : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function delete( string $key ): bool {
		unset( $this->items[ $key ] );

		return delete_option( $this->normalize_key( $key ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function delete_by_tags( array $tags ): bool {
		foreach ( $tags as $tag ) {
			$normalized_tag = $this->normalize_key( $tag, 'tag' );

			foreach ( (array) get_option( $normalized_tag, [] ) as $item_key ) {
				$this->delete( $item_key );
			}

			delete_option( $normalized_tag );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function clear(): bool {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} a WHERE a.option_name LIKE %s OR a.option_name LIKE %s",
				$wpdb->esc_like( self::TAG_PREFIX ) . '_%',
				$wpdb->esc_like( self::CACHE_KEY_PREFIX ) . '_%',
			)
		);

		return true;
	}
}
