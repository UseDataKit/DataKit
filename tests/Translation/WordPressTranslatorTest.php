<?php

namespace DataKit\Plugin\Tests\Translation;

use DataKit\DataViews\Data\ArrayDataSource;
use DataKit\DataViews\Data\Exception\DataNotFoundException;
use DataKit\Plugin\Translation\WordPressTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see WordPressTranslator}
 *
 * @since $ver$
 */
final class WordPressTranslatorTest extends TestCase {

	/**
	 * Test case for {@see WordPressTranslator::translate()}.
	 *
	 * @since $ver$
	 */
	public function test_translate(): void {
		$translator = new WordPressTranslator();
		$e          = DataNotFoundException::with_id( new ArrayDataSource( 'test', [] ), 'some-id' );
		self::assertSame( 'Data for key "some-id" not found.', $e->translate( $translator ) );
	}
}
