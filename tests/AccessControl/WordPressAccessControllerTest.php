<?php

namespace DataKit\Plugin\Tests\AccessControl;

use DataKit\DataViews\AccessControl\Capability\DeleteDataView;
use DataKit\DataViews\AccessControl\Capability\EditDataView;
use DataKit\DataViews\AccessControl\Capability\ViewDataView;
use DataKit\DataViews\Data\ArrayDataSource;
use DataKit\DataViews\DataView\DataView;
use DataKit\Plugin\AccessControl\WordPressAccessController;
use PHPUnit\Framework\TestCase;
use WP_User;

/**
 * Unit tests for {@see WordPressAccessController}
 *
 * @since $ver$
 */
final class WordPressAccessControllerTest extends TestCase {
	/**
	 * Test case for {@see WordPressAccessController::can()}.
	 *
	 * @since $ver$
	 */
	public function test_can(): void {
		$dataview = DataView::table( 'test', new ArrayDataSource( 'test', [] ), [] );
		$user     = new WP_User( (object) [ 'ID' => 1 ], 'admin' );
		$user->add_cap( 'administrator' );

		$guest = new WordPressAccessController( null );
		$admin = new WordPressAccessController( $user );

		self::assertTrue( $guest->can( new ViewDataView( $dataview ) ) );
		self::assertFalse( $guest->can( new EditDataView( $dataview ) ) );
		self::assertFalse( $guest->can( new DeleteDataView( $dataview ) ) );

		self::assertTrue( $admin->can( new EditDataView( $dataview ) ) );
		self::assertTrue( $admin->can( new DeleteDataView( $dataview ) ) );
	}
}
