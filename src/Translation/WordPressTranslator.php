<?php

namespace DataKit\Plugin\Translation;

use DataKit\DataViews\Translation\ReplaceParameters;
use DataKit\DataViews\Translation\Translator;

/**
 * A translator instance backed by WordPress translations.
 *
 * @since $ver$
 */
final class WordPressTranslator implements Translator {
	use ReplaceParameters;

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function translate( string $message, array $parameters = [] ): string {
		$messages = [
			'datakit.action.forbidden'         => __( 'Action is forbidden.', 'datakit' ),
			'datakit.action.forbidden.with_id' => _x(
				'Action is forbidden for "[id]".',
				'Placeholders inside [] are not to be translated.',
				'datakit'
			),
			'datakit.data_source.not_found'    => __( 'DataSource not found.', 'datakit' ),
			'datakit.data.not_found'           => __( 'Data not found.', 'datakit' ),
			'datakit.data.not_found.with_id'   => _x(
				'Data for key "[id]" not found.',
				'Placeholders inside [] are not to be translated.',
				'datakit'
			),
			'datakit.dataview.not_found'       => __( 'DataView not found.', 'datakit' ),
		];

		$translation = $messages[ $message ] ?? $message;

		return $this->replace_parameters( $translation, $parameters );
	}
}
