<?php

namespace Wikibase\Search\Elastic\Tests;

use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use SpecialPageTestBase;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\TermIndexEntry;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\SpecialEntitiesWithoutPage;

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
		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutLabel',
			TermIndexEntry::TYPE_LABEL,
			'wikibasecirrus-entitieswithoutlabel-legend',
			[ 'item', 'property' ],
			new StaticContentLanguages( [ 'acceptedlanguage' ] ),
			WikibaseRepo::getLanguageNameLookupFactory()->getForAutonyms(),
			WikibaseRepo::getEntityNamespaceLookup()
		);
	}

	public function testForm() {
		[ $html, ] = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertStringContainsString( '(wikibasecirrus-entitieswithoutlabel-label-language)', $html );
		$this->assertStringContainsString( 'name=\'language\'', $html );
		$this->assertStringContainsString( 'id=\'wb-entitieswithoutpage-language\'', $html );
		$this->assertStringContainsString( 'wb-language-suggester', $html );

		$this->assertStringContainsString( '(wikibasecirrus-entitieswithoutlabel-label-type)', $html );
		$this->assertStringContainsString( 'name=\'type\'', $html );
		$this->assertStringContainsString( 'id=\'wb-entitieswithoutpage-type\'', $html );
		$this->assertStringContainsString( '(wikibasecirrus-entity-item)', $html );

		$this->assertStringContainsString( '(wikibasecirrus-entitieswithoutlabel-submit)', $html );
		$this->assertStringContainsString( 'id=\'wikibasecirrus-entitieswithoutpage-submit\'', $html );
	}

	public function testRequestParameters() {
		$request = new FauxRequest( [
			'language' => "''LANGUAGE''",
			'type' => "''TYPE''",
		] );
		[ $html, ] = $this->executeSpecialPage( '', $request );

		$this->assertStringContainsString( '&#39;&#39;LANGUAGE&#39;&#39;', $html );
		$this->assertStringContainsString( '&#39;&#39;TYPE&#39;&#39;', $html );
		$this->assertStringNotContainsString( "''LANGUAGE''", $html );
		$this->assertStringNotContainsString( "''TYPE''", $html );
		$this->assertStringNotContainsString( '&amp;', $html, 'no double escaping' );
	}

	public function testSubPageParts() {
		[ $html, ] = $this->executeSpecialPage( "''LANGUAGE''/''TYPE''" );

		$this->assertStringContainsString( '&#39;&#39;LANGUAGE&#39;&#39;', $html );
		$this->assertStringContainsString( '&#39;&#39;TYPE&#39;&#39;', $html );
	}

	public function testNoParams() {
		[ $html, ] = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertStringNotContainsString( 'class="mw-spcontent"', $html );
		$this->assertStringNotContainsString( '(htmlform-invalid-input)', $html );
	}

	public function testNoLanguage() {
		$request = new FauxRequest( [ 'type' => 'item' ] );
		[ $html, ] = $this->executeSpecialPage( '', $request, 'qqx' );

		$this->assertStringNotContainsString( 'class="mw-spcontent"', $html );
		$this->assertStringNotContainsString( '(htmlform-invalid-input)', $html );
	}

	public function testNoType() {
		[ $html, ] = $this->executeSpecialPage( 'acceptedlanguage', null, 'qqx' );

		$this->assertStringNotContainsString( 'class="mw-spcontent"', $html );
		$this->assertStringNotContainsString( '(htmlform-invalid-input)', $html );
	}

	public function testInvalidLanguage() {
		[ $html, ] = $this->executeSpecialPage( "''INVALID''", null, 'qqx' );

		$this->assertStringContainsString(
			'(wikibasecirrus-entitieswithoutlabel-invalid-language: &#39;&#39;INVALID&#39;&#39;)',
			$html
		);
	}

	public function testValidLanguage() {
		$request = new FauxRequest( [ 'type' => 'item' ] );

		/** @var FauxResponse $response */
		[ , $response ] = $this->executeSpecialPage( 'acceptedlanguage', $request, 'qqx' );
		$target = $response->getHeader( 'Location' );

		$this->assertStringContainsString( 'search=-haslabel:acceptedlanguage', $target );
		$this->assertStringContainsString( 'sort=relevance', $target );
	}

	public function testInvalidType() {
		[ $html, ] = $this->executeSpecialPage( "acceptedlanguage/''INVALID''", null, 'qqx' );

		$this->assertStringContainsString(
			'(wikibasecirrus-entitieswithoutlabel-invalid-type: &#39;&#39;INVALID&#39;&#39;)',
			$html
		);
	}

}
