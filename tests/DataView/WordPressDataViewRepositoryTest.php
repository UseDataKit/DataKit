<?php

namespace DataKit\Plugin\Tests\DataView;

use DataKit\DataViews\Data\ArrayDataSource;
use DataKit\DataViews\DataView\ArrayDataViewRepository;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewNotFoundException;
use DataKit\Plugin\DataView\WordPressDataViewRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see WordPressDataViewRepository}
 *
 * @since $ver$
 */
final class WordPressDataViewRepositoryTest extends TestCase {
	/**
	 * Test case for {@see WordPressDataViewRepository}.
	 *
	 * @since $ver$
	 */
	public function test_collection(): void {
		$data         = new ArrayDataSource( 'data', [] );
		$repository_1 = new ArrayDataViewRepository( [ $table_1 = DataView::table( 'table_1', $data, [] ) ] );
		$repository_2 = new ArrayDataViewRepository( [ $table_2 = DataView::table( 'table_2', $data, [] ) ] );

		$wordpress_repository = new WordPressDataViewRepository( $repository_1, $repository_2 );
		$wordpress_repository->save( $table_3 = DataView::table( 'table_3', $data, [] ) );

		self::assertTrue( $wordpress_repository->has( 'table_1' ) );
		self::assertTrue( $wordpress_repository->has( 'table_2' ) );
		self::assertTrue( $wordpress_repository->has( 'table_3' ) );

		self::assertFalse( $wordpress_repository->has( 'missing' ) );
		self::assertEquals( $data, $wordpress_repository->get( 'table_1' )->data_source() );
		self::assertSame( compact( 'table_1', 'table_2', 'table_3' ), $wordpress_repository->all() );

		$wordpress_repository->delete( $table_1 );
		self::assertFalse( $wordpress_repository->has( 'table_1' ) );
		self::assertFalse( $repository_1->has( 'table_1' ) );

		$this->expectException( DataViewNotFoundException::class );
		$wordpress_repository->get( 'missing' );
	}
}
