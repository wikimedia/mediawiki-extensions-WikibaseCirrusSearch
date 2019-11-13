<?php

namespace Wikibase\Search\Elastic\Tests;

use FauxRequest;
use FauxResponse;
use SpecialPageTestBase;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\SpecialEntitiesWithoutPage;
use Wikibase\TermIndexEntry;

/**
 * @covers \Wikibase\Search\Elastic\SpecialEntitiesWithoutPage
 *
 * @group Wikibase
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @group Database
 *        ^---- needed because we rely on Title objects internally
 *
 * @license GPL-2.0-or-later
 */
class SpecialEntitiesWithoutPageTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutLabel',
			TermIndexEntry::TYPE_LABEL,
			'wikibase-entitieswithoutlabel-legend',
			[ 'item', 'property' ],
			new StaticContentLanguages( [ 'acceptedlanguage' ] ),
			new LanguageNameLookup(),
			$wikibaseRepo->getEntityNamespaceLookup()
		);
	}

	public function testForm() {
		list( $html, ) = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertContains( '(wikibase-entitieswithoutlabel-label-language)', $html );
		$this->assertContains( 'name=\'language\'', $html );
		$this->assertContains( 'id=\'wb-entitieswithoutpage-language\'', $html );
		$this->assertContains( 'wb-language-suggester', $html );

		$this->assertContains( '(wikibase-entitieswithoutlabel-label-type)', $html );
		$this->assertContains( 'name=\'type\'', $html );
		$this->assertContains( 'id=\'wb-entitieswithoutpage-type\'', $html );
		$this->assertContains( '(wikibase-entity-item)', $html );

		$this->assertContains( '(wikibase-entitieswithoutlabel-submit)', $html );
		$this->assertContains( 'id=\'wikibase-entitieswithoutpage-submit\'', $html );
	}

	public function testRequestParameters() {
		$request = new FauxRequest( [
			'language' => "''LANGUAGE''",
			'type' => "''TYPE''",
		] );
		list( $html, ) = $this->executeSpecialPage( '', $request );

		$this->assertContains( '&#39;&#39;LANGUAGE&#39;&#39;', $html );
		$this->assertContains( '&#39;&#39;TYPE&#39;&#39;', $html );
		$this->assertNotContains( "''LANGUAGE''", $html );
		$this->assertNotContains( "''TYPE''", $html );
		$this->assertNotContains( '&amp;', $html, 'no double escaping' );
	}

	public function testSubPageParts() {
		list( $html, ) = $this->executeSpecialPage( "''LANGUAGE''/''TYPE''" );

		$this->assertContains( '&#39;&#39;LANGUAGE&#39;&#39;', $html );
		$this->assertContains( '&#39;&#39;TYPE&#39;&#39;', $html );
	}

	public function testNoParams() {
		list( $html, ) = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertNotContains( 'class="mw-spcontent"', $html );
		$this->assertNotContains( '(htmlform-invalid-input)', $html );
	}

	public function testNoLanguage() {
		$request = new FauxRequest( [ 'type' => 'item' ] );
		list( $html, ) = $this->executeSpecialPage( '', $request, 'qqx' );

		$this->assertNotContains( 'class="mw-spcontent"', $html );
		$this->assertNotContains( '(htmlform-invalid-input)', $html );
	}

	public function testNoType() {
		list( $html, ) = $this->executeSpecialPage( 'acceptedlanguage', null, 'qqx' );

		$this->assertNotContains( 'class="mw-spcontent"', $html );
		$this->assertNotContains( '(htmlform-invalid-input)', $html );
	}

	public function testInvalidLanguage() {
		list( $html, ) = $this->executeSpecialPage( "''INVALID''", null, 'qqx' );

		$this->assertContains(
			'(wikibase-entitieswithoutlabel-invalid-language: &#39;&#39;INVALID&#39;&#39;)',
			$html
		);
	}

	public function testValidLanguage() {
		$request = new FauxRequest( [ 'type' => 'item' ] );

		/** @var \FauxResponse $response */
		list( , $response ) = $this->executeSpecialPage( 'acceptedlanguage', $request, 'qqx' );
		$target = $response->getHeader( 'Location' );

		$this->assertContains( 'search=-haslabel:acceptedlanguage', $target );
		$this->assertContains( 'sort=relevance', $target );
	}

	public function testInvalidType() {
		list( $html, ) = $this->executeSpecialPage( "acceptedlanguage/''INVALID''", null, 'qqx' );

		$this->assertContains(
			'(wikibase-entitieswithoutlabel-invalid-type: &#39;&#39;INVALID&#39;&#39;)',
			$html
		);
	}

}
