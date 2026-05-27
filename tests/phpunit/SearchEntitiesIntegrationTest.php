<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\CirrusDebugOptions;
use MediaWiki\Language\Language;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\DispatchingWbSearchEntitiesController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesRequest;
use Wikibase\Search\Elastic\EntitySearchElastic;

/**
 * @covers \Wikibase\Search\Elastic\EntitySearchElastic
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class SearchEntitiesIntegrationTest extends ApiTestCase {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkipped( 'Temporarily skipped to rename a class' );

		global $wgWBRepoSettings;

		// Test as if the default federation type was entity source based.
		$settings = $wgWBRepoSettings;
		$settings['useEntitySourceBasedFederation'] = true;
		$this->setMwGlobals( [
			'wgWBRepoSettings' => $settings,
			'wgWBCSUseCirrus' => true,
		] );
		$this->idParser = new BasicEntityIdParser();
	}

	public static function provideQueriesForEntityIds() {
		return [
			'Exact item ID' => [
				'Q1',
				Item::ENTITY_TYPE,
				[ 'Q1' ]
			],
			'Lower case item ID' => [
				'q2',
				Item::ENTITY_TYPE,
				[ 'Q2' ]
			],

			'Exact property ID' => [
				'P1',
				Property::ENTITY_TYPE,
				[ 'P1' ]
			],
			'Lower case property ID' => [
				'p2',
				Property::ENTITY_TYPE,
				[ 'P2' ]
			],

			'Copy paste with brackets' => [
				'(Q3)',
				Item::ENTITY_TYPE,
				[ 'Q3' ]
			],
			'Copy pasted concept URI' => [
				'http://www.wikidata.org/entity/Q4',
				Item::ENTITY_TYPE,
				[ 'Q4' ]
			],
			'Copy pasted page URL' => [
				'https://www.wikidata.org/wiki/Q5',
				Item::ENTITY_TYPE,
				[ 'Q5' ]
			],
		];
	}

	/**
	 * @dataProvider provideQueriesForEntityIds
	 */
	public function testElasticSearchIntegration( string $query, string $type, array $expectedIds ): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$mockWbSearchEntitiesController = $this->createStub( DispatchingWbSearchEntitiesController::class );
		$mockWbSearchEntitiesController->method( 'getControllerForEntityType' )
			->willReturnCallback( $this->makeSearchControllerCallback() );
		$this->setService( 'WbSearch.DispatchingWbSearchEntitiesController', $mockWbSearchEntitiesController );

		[ $resultData ] = $this->doApiRequest( [
			'action' => 'wbsearchentities',
			'language' => 'en',
			'search' => $query,
			'type' => $type
		] );
		$this->assertSameSearchResults( $resultData, $expectedIds );
	}

	/**
	 * Create callback that returns a mock search controller by type
	 *
	 * @return \Closure
	 */
	private function makeSearchControllerCallback(): \Closure {
		return function ( $type ) {
			$mockSearchController = $this->createStub( WbSearchEntitiesController::class );
			$mockSearchController->method( 'search' )
				->willReturnCallback( $this->makeSearchResultsCallback( $type ) );

			return $mockSearchController;
		};
	}

	/**
	 * Create callback that transforms JSON query return to TermSearchResult[]
	 *
	 * @return \Closure
	 */
	private function makeSearchResultsCallback( string $type ) {
		$entitySearchElastic = $this->newEntitySearchElastic();

		return function ( WbSearchEntitiesRequest $request )
				use ( $entitySearchElastic, $type ) {
			$result = $entitySearchElastic->getRankedSearchResults(
				$request->text,
				$request->searchLanguageCode,
				$type,
				$request->limit,
				$request->strictLanguage
			);
			// Transitional, query dumps will always be wrapped in an array

			$result = $result['__main__'] ?? $result;
			// FIXME: this is very brittle, but I don't know how to make it better.
			$matchId = $result['query']['query']['bool']['should'][1]['term']['title.keyword'];
			try {
				$entityId = $this->idParser->parse( $matchId );
			} catch ( EntityIdParsingException $ex ) {
				return [];
			}

			return [ new TermSearchResult( new Term( $request->searchLanguageCode, $matchId ), '', $entityId ) ];
		};
	}

	/**
	 * @return EntitySearchElastic
	 */
	private function newEntitySearchElastic() {
		$entitySearchElastic = new EntitySearchElastic(
			$this->newLanguageFallbackChainFactory(),
			$this->idParser,
			$this->createMock( Language::class ),
			[ 'item' => 'wikibase-item', 'property' => 'wikibase-property' ],
			new FauxRequest(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests()
		);

		return $entitySearchElastic;
	}

	/**
	 * @param array[] $resultData
	 */
	private function assertSameSearchResults( array $resultData, array $expectedIds ) {
		$this->assertSameSize( $expectedIds, $resultData['search'] );

		foreach ( $expectedIds as $index => $expectedId ) {
			$this->assertSame( $expectedId, $resultData['search'][$index]['id'] );
		}
	}

	/**
	 * @return LanguageFallbackChainFactory
	 */
	private function newLanguageFallbackChainFactory() {
		$stubContentLanguages = $this->createStub( ContentLanguages::class );
		$stubContentLanguages->method( 'hasLanguage' )
			->willReturn( true );

		$fallbackChain = $this->getMockBuilder( TermLanguageFallbackChain::class )
			->setConstructorArgs( [ [], $stubContentLanguages ] )
			->onlyMethods( [ 'getFetchLanguageCodes' ] )
			->getMock();
		$fallbackChain->method( 'getFetchLanguageCodes' )
			->willReturn( [ 'phpunit_lang' ] );

		$factory = $this->createMock( LanguageFallbackChainFactory::class );
		$factory->method( $this->logicalOr( 'newFromLanguage', 'newFromLanguageCode' ) )
			->willReturn( $fallbackChain );

		return $factory;
	}

}
