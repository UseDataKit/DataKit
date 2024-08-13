<?php

namespace DataKit\Plugin\DataView;

use DataKit\DataViews\DataView\ArrayDataViewRepository;
use DataKit\DataViews\DataView\DataView;
use DataKit\DataViews\DataView\DataViewNotFoundException;
use DataKit\DataViews\DataView\DataViewRepository;

/**
 * A DataView repository that manages multiple registered {@see DataViewRepository} instances.
 *
 * @since $ver$
 */
final class WordPressDataViewRepository implements DataViewRepository {
	/**
	 * The collection of repositories.
	 *
	 * @since $ver$
	 * @var DataViewRepository[]
	 */
	private array $repositories;

	/**
	 * Repository used to register extra DataViews.
	 *
	 * @since $ver$
	 * @var ArrayDataViewRepository
	 */
	private ArrayDataViewRepository $inner;

	/**
	 * Creates the repository.
	 *
	 * @since $ver$
	 *
	 * @param DataViewRepository ...$repositories The collection of repositories.
	 */
	public function __construct( DataViewRepository ...$repositories ) {
		$this->repositories = (array) apply_filters( 'datakit/repositories', $repositories );
		$this->inner        = new ArrayDataViewRepository();
	}

	/**
	 * Returns the registered repositories and the inner repository last.
	 *
	 * @since $ver$
	 *
	 * @return DataViewRepository[] The repositories.
	 */
	private function repositories(): array {
		return [ ...$this->repositories, $this->inner ];
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function all(): array {
		$result = [];
		foreach ( $this->repositories() as $repository ) {
			$result[] = $repository->all();
		}

		return array_merge( [], ...$result );
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function get( string $id ): DataView {
		foreach ( $this->repositories() as $repository ) {
			if ( $repository->has( $id ) ) {
				return $repository->get( $id );
			}
		}

		throw new DataViewNotFoundException();
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function has( string $id ): bool {
		foreach ( $this->repositories() as $repository ) {
			if ( $repository->has( $id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function save( DataView $data_view ): void {
		$this->inner->save( $data_view );
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function delete( DataView $data_view ): void {
		foreach ( $this->repositories() as $repository ) {
			if ( $this->has( $data_view->id() ) ) {
				$repository->delete( $data_view );
			}
		}
	}
}
