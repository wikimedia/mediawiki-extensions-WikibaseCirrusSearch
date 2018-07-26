<?php
namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch;
use Wikibase\Repo\WikibaseRepo;

/**
 * Mixin for tests that could collide with Wikibase CirrusSearch functionality.
 * After migration is complete, this class will not be necessary anymore.
 */
trait WikibaseSearchTestCase {

	private $oldDisableCirrus;

	public function disableWikibaseNative() {
		// Temporary for switch period - disable native Wikibase CirrusSearch support
		// to avoid collisions
		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$this->oldDisableCirrus = $settings->getSetting( 'disableCirrus' );
		$settings->setSetting( 'disableCirrus', true );
		// Enable WBSearch hooks
		$this->setMwGlobals( 'wgWikibaseCirrusSearchEnable', true );
	}

	// Declare dependency on setMwGlobals
	abstract public function setMwGlobals( $pairs, $value = null );

	public function setUp() {
		parent::setUp();
		if ( !class_exists( CirrusSearch::class ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
		$this->disableWikibaseNative();
	}

	public function tearDown() {
		if ( !is_null( $this->oldDisableCirrus ) ) {
			// null means we somehow skipped setup. Leave it alone then.
			$settings = WikibaseRepo::getDefaultInstance()->getSettings();
			$settings->setSetting( 'disableCirrus', $this->oldDisableCirrus );
		}
		parent::tearDown();
	}

}
