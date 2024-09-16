<?php

namespace DataKit\Plugin\AccessControl;

use DataKit\DataViews\AccessControl\AccessController;
use DataKit\DataViews\AccessControl\Capability\Capability;
use DataKit\DataViews\AccessControl\ReadOnlyAccessController;
use WP_User;

/**
 * Access Controller backed by a {@see WP_User}.
 *
 * @since $ver$
 */
final class WordPressAccessController implements AccessController {
	/**
	 * The user to test against.
	 *
	 * @since $ver$
	 *
	 * @var WP_User|null
	 */
	private ?WP_User $user;

	/**
	 * The previous access controller.
	 *
	 * @since $ver$
	 *
	 * @var AccessController
	 */
	private AccessController $previous;

	/**
	 * Creates the Access Controller.
	 *
	 * @since $ver$
	 *
	 * @param WP_User|null $user The user to test against.
	 */
	public function __construct( ?WP_User $user ) {
		$this->user     = $user;
		$this->previous = new ReadOnlyAccessController();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since $ver$
	 */
	public function can( Capability $capability ): bool {
		$can = $this->previous->can( $capability );

		if ( $this->user && $this->user->exists() ) {
			$can = $this->user->has_cap( 'administrator' );
		}

		return $this->filter_result( $can, $capability );
	}

	/**
	 * Applies filter on the result.
	 *
	 * @since $ver$
	 *
	 * @param bool       $can        The result to return.
	 * @param Capability $capability The capability.
	 *
	 * @return bool The filtered result.
	 */
	private function filter_result( bool $can, Capability $capability ): bool {
		/**
		 * Modifies the capability check.
		 *
		 * @filter `datakit/acl/can`
		 *
		 * @since  $ver$
		 *
		 * @param bool       $can        Whether the user can.
		 * @param Capability $capability Whether the user can.
		 * @param ?WP_User   $user       The WP_User.
		 */
		return (bool) apply_filters( 'datakit/access-control/can', $can, $capability, $this->user );
	}
}
