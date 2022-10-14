<?php

namespace Wikibase\Search\Elastic\Tests;

use Language;
use MediaWikiIntegrationTestCase;
use Title;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\FallbackLabelDescriptionLookup;
use Wikibase\Lib\Store\FallbackLabelDescriptionLookupFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Hooks;

/**
 * @covers \Wikibase\Search\Elastic\Hooks
 */
class OpenSearchLabelsTest extends MediaWikiIntegrationTestCase {

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

		$this->mockWikibaseRepoServices( $lang, $labels );
		Hooks::amendSearchResults( $lang, $results );

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
		$mockLookup = $this->createMock( EntityNamespaceLookup::class );
		$mockLookup->method( 'isEntityNamespace' )->willReturnCallback( static function ( $ns ) {
			return $ns < 10;
		} );

		return $mockLookup;
	}

	private function getLabelDescriptionLookup( $language, array $labels ) {
		$mock = $this->createMock( FallbackLabelDescriptionLookup::class );

		$mock->method( 'getLabel' )->willReturnCallback(
			static function ( EntityId $id ) use ( $labels, $language ) {
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
	private function mockWikibaseRepoServices( Language $language, array $labels ) {
		// Description lookup
		$lookupFactory = $this->createMock( FallbackLabelDescriptionLookupFactory::class );
		$lookupFactory->method( 'newLabelDescriptionLookup' )
			->with( $language )
			->willReturn( $this->getLabelDescriptionLookup( $language->getCode(), $labels ) );
		$this->setService( 'WikibaseRepo.FallbackLabelDescriptionLookupFactory',
			$lookupFactory );

		// Entity ID Parser
		$parser = new BasicEntityIdParser();
		$this->setService( 'WikibaseRepo.EntityIdParser', $parser );
		$this->setService( 'WikibaseRepo.EntityNamespaceLookup',
			$this->getMockEntityNamespaceLookup() );
	}

}
