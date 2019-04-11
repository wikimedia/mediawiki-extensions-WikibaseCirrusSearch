<?php
namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch;

/**
 * Mixin for tests that could collide with Wikibase CirrusSearch functionality.
 * After migration is complete, this class will not be necessary anymore.
 */
trait WikibaseSearchTestCase {

	private $oldDisableCirrus;

	public function enableWBCS() {
		// Enable WBSearch hooks
		$this->setMwGlobals( 'wgWBCSUseCirrus', true );
	}

	// Declare dependency on setMwGlobals
	abstract public function setMwGlobals( $pairs, $value = null );

	public function setUp() {
		parent::setUp();
		if ( !class_exists( CirrusSearch::class ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
		$this->enableWBCS();
	}

}
