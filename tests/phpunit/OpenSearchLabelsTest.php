<?php

namespace Wikibase\Search\Elastic\Tests;

use Language;
use MediaWikiTestCase;
use Title;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Hooks;

/**
 * @covers \Wikibase\Search\Elastic\Hooks
 */
class OpenSearchLabelsTest extends MediaWikiTestCase {

	public function getOpenSearchData() {
		return [
			"one result" => [
				'en',
				[
					[ 'title' => Title::makeTitle( NS_MAIN, "Q1" ) ],
				],
				[ 'Q1' => 'duck' ],
				[ 'Q1' => 'duck (Q1)' ],
			],
			"different language" => [
				'qqq',
				[
					[ 'title' => Title::makeTitle( NS_MAIN, "Q1" ) ],
				],
				[ 'Q1' => 'duck' ],
				[ 'Q1' => 'duck (Q1)' ],
			],
			"two results" => [
				'en',
				[
					[ 'title' => Title::makeTitle( NS_MAIN, "Q1" ) ],
					[ 'title' => Title::makeTitle( NS_MAIN, "Q2" ) ],
				],
				[ 'Q1' => 'duck', 'Q2' => 'goose' ],
				[ 'Q1' => 'duck (Q1)', 'Q2' => 'goose (Q2)' ],
			],
			"no label" => [
				'en',
				[
					[ 'title' => Title::makeTitle( NS_MAIN, "Q1" ) ],
					[ 'title' => Title::makeTitle( NS_MAIN, "Q2" ) ],
					[ 'title' => Title::makeTitle( NS_MAIN, "Q3" ) ],
				],
				[ 'Q1' => 'duck', 'Q3' => 'goose' ],
				[ 'Q1' => 'duck (Q1)', 'Q3' => 'goose (Q3)' ],
			],
			"namespaces " => [
				'en',
				[
					[ 'title' => Title::makeTitle( 2, "Q1" ) ],
					[ 'title' => Title::makeTitle( 3, "Q2" ) ],
					[ 'title' => Title::makeTitle( 13, "Q3" ) ],
				],
				[ 'Q1' => 'duck', 'Q3' => 'goose' ],
				[ 'User:Q1' => 'duck (Q1)' ],
			],
			"no labels" => [
				'en',
				[
					[ 'title' => Title::makeTitle( NS_MAIN, "Q1" ) ],
					[ 'title' => Title::makeTitle( NS_MAIN, "Q2" ) ],
				],
				[],
				[],
			],
		];
	}

	/**
	 * @param string $language
	 * @param array $results
	 * @param string[] $labels Labels existing in the system, ID => label
	 * @param string[] $expected Expected terms
	 * @dataProvider getOpenSearchData
	 */
	public function testOpenSearch( $language, $results, $labels, $expected ) {
		$lang = Language::factory( $language );
		$repo = $this->getWikibaseRepo( $lang, $labels );
		Hooks::amendSearchResults( $repo, $lang, $results );

		$resultsByTitle = [];
		foreach ( $results as $result ) {
			if ( isset( $result['extract'] ) ) {
				$resultsByTitle[(string)$result['title']] = $result['extract'];
			}
		}

		$this->assertEquals( $expected, $resultsByTitle );
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	private function getMockEntityNamespaceLookup() {
		$mockLookup = $this->getMockBuilder( EntityNamespaceLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mockLookup->method( 'isEntityNamespace' )->willReturnCallback( function ( $ns ) {
			return $ns < 10;
		} );

		return $mockLookup;
	}

	private function getLabelDescriptionLookup( $language, array $labels ) {
		$mock = $this->getMockBuilder( LanguageFallbackLabelDescriptionLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$mock->method( 'getLabel' )->willReturnCallback(
			function ( EntityId $id ) use ( $labels, $language ) {
				if ( isset( $labels[$id->getSerialization()] ) ) {
					return new TermFallback( $language, $labels[$id->getSerialization()], $language,
						$language );
				} else {
					return null;
				}
			} );

		return $mock;
	}

	/**
	 * @param Language $language
	 * @param array $labels
	 * @return WikibaseRepo
	 */
	private function getWikibaseRepo( Language $language, array $labels ) {
		$repo = WikibaseRepo::getDefaultInstance();
		$mock = $this->getMockBuilder( WikibaseRepo::class )
			->disableOriginalConstructor()
			->getMock();

		// Description lookup
		$lookupFactory = $this->getMockBuilder( LanguageFallbackLabelDescriptionLookupFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$lookupFactory->method( 'newLabelDescriptionLookup' )
			->with( $language )
			->willReturn( $this->getLabelDescriptionLookup( $language->getCode(), $labels ) );
		$mock->method( 'getLanguageFallbackLabelDescriptionLookupFactory' )
			->willReturn( $lookupFactory );

		// Entity ID Parser
		$parser = new BasicEntityIdParser();
		$mock->method( 'getEntityIdParser' )->willReturn( $parser );
		// getEntityNamespaceLookup
		$mock->method( 'getEntityNamespaceLookup' )
			->willReturn( $this->getMockEntityNamespaceLookup() );
		// getEntityLinkFormatterFactory
		// Use real one here
		$mock->method( 'getEntityLinkFormatterFactory' )
			->willReturn( $repo->getEntityLinkFormatterFactory( $language ) );

		return $mock;
	}

}
