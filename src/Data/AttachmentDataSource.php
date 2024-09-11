<?php

namespace DataKit\Plugin\Data;

use DataKit\DataViews\Data\CsvDataSource;
use DataKit\DataViews\Data\Exception\DataSourceNotFoundException;

/**
 * Data source (factory) backed by a WordPress media attachment.
 *
 * @since $ver$
 */
final class AttachmentDataSource {
	/**
	 * Disabled constructor.
	 *
	 * Use any of the named constructors.
	 *
	 * @since $ver$
	 */
	private function __construct() {
	}

	/**
	 * Returns a {@see CsvDataSource} for the specified attachment ID.
	 *
	 * @since $ver$
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return CsvDataSource THe data source.
	 * @throws DataSourceNotFoundException When the attachment could not be found.
	 */
	public static function csv(
		int $attachment_id,
		string $separator = ',',
		string $enclosure = '"',
		string $escape = '\\'
	): CsvDataSource {
		$path = get_attached_file( $attachment_id );
		if ( ! $path ) {
			// translators: %d is the attachment ID.
			throw new DataSourceNotFoundException( sprintf( esc_html__( 'The attachment ID (%d) is not found.', 'datakit' ), $attachment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return new CsvDataSource( $path, $separator, $enclosure, $escape );
	}
}
