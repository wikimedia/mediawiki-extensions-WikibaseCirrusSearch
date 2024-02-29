<?php
namespace Wikibase\Search\Elastic\Tests;

use MediaWiki\MainConfigNames;

/**
 * Mixin for tests that could collide with Wikibase CirrusSearch functionality.
 * After migration is complete, this class will not be necessary anymore.
 */
trait WikibaseSearchTestCase {
	public function enableWBCS() {
		// Enable WBSearch hooks
		$this->setMwGlobals( 'wgWBCSUseCirrus', true );
	}

	// Declare dependency on setMwGlobals
	abstract public function setMwGlobals( $pairs, $value = null );

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValue( MainConfigNames::UsePigLatinVariant, false );
		$this->enableWBCS();
	}

}
