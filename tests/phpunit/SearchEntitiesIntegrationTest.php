<?php

namespace Wikibase\Search\Elastic\Tests;

use ApiMain;
use CirrusSearch\CirrusSearch;
use CirrusSearch\CirrusDebugOptions;
use ExtensionRegistry;
use FauxRequest;
use Language;
use MediaWikiTestCase;
use RequestContext;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\LanguageFallbackChain;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\Store\EntityArticleIdLookup;
use Wikibase\Lib\Store\EntityTitleTextLookup;
use Wikibase\Lib\Store\EntityUrlLookup;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Api\SearchEntities;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\WikibaseRepo;
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
class SearchEntitiesIntegrationTest extends MediaWikiTestCase {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	protected function setUp() : void {
		parent::setUp();

		global $wgWBRepoSettings;

		// Test as if the default federation type was entity source based.
		$settings = $wgWBRepoSettings;
		$settings['useEntitySourceBasedFederation'] = true;
		$this->setMwGlobals( 'wgWBRepoSettings', $settings );
		WikibaseRepo::resetClassStatics();

		$this->setMwGlobals( 'wgWBCSUseCirrus', true );
		$this->idParser = new BasicEntityIdParser();
	}

	public function provideQueriesForEntityIds() {
		return [
			'Exact item ID' => [
				'Q1',
				[ 'Q1' ]
			],
			'Lower case item ID' => [
				'q2',
				[ 'Q2' ]
			],

			'Exact property ID' => [
				'P1',
				[ 'P1' ]
			],
			'Lower case property ID' => [
				'p2',
				[ 'P2' ]
			],

			'Copy paste with brackets' => [
				'(Q3)',
				[ 'Q3' ]
			],
			'Copy pasted concept URI' => [
				'http://www.wikidata.org/entity/Q4',
				[ 'Q4' ]
			],
			'Copy pasted page URL' => [
				'https://www.wikidata.org/wiki/Q5',
				[ 'Q5' ]
			],
		];
	}

	/**
	 * @dataProvider provideQueriesForEntityIds
	 */
	public function testElasticSearchIntegration( $query, array $expectedIds ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}

		$mockEntitySearchElastic = $this->getMockBuilder( EntitySearchElastic::class )
				->disableOriginalConstructor()
				->setMethods( [ 'getRankedSearchResults' ] )
				->getMock();

		$mockEntitySearchElastic->method( 'getRankedSearchResults' )
			->willReturnCallback( $this->makeElasticSearchCallback() );

		$resultData = $this->executeApiModule( $mockEntitySearchElastic, $query );
		$this->assertSameSearchResults( $resultData, $expectedIds );
	}

	/**
	 * Create callback that transforms JSON query return to TermSearchResult[]
	 *
	 * @return \Closure
	 */
	private function makeElasticSearchCallback() {
		$entitySearchElastic = $this->newEntitySearchElastic();

		return function ( $text, $languageCode, $entityType, $limit, $strictLanguage )
				use ( $entitySearchElastic ) {
			$result = $entitySearchElastic->getRankedSearchResults(
				$text,
				$languageCode,
				$entityType,
				$limit,
				$strictLanguage
			);
			// comes out as JSON data
			$resultData = json_decode( $result, true );
			// Transitional, query dumps will always be wrapped in an array

			$resultData = $resultData['__main__'] ?? $resultData;
			// FIXME: this is very brittle, but I don't know how to make it better.
			$matchId = $resultData['query']['query']['bool']['should'][1]['term']['title.keyword'];
			try {
				$entityId = $this->idParser->parse( $matchId );
			} catch ( EntityIdParsingException $ex ) {
				return [];
			}

			return [ new TermSearchResult( new Term( $languageCode, $matchId ), '', $entityId ) ];
		};
	}

	/**
	 * @return EntitySearchElastic
	 */
	private function newEntitySearchElastic() {
		$entitySearchElastic = new EntitySearchElastic(
			$this->newLanguageFallbackChainFactory(),
			$this->idParser,
			$this->getMockBuilder( Language::class )->disableOriginalConstructor()->getMock(),
			[ 'item' => 'wikibase-item' ],
			new FauxRequest(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests()
		);

		return $entitySearchElastic;
	}

	/**
	 * @param array[] $resultData
	 */
	private function assertSameSearchResults( array $resultData, array $expectedIds ) {
		$this->assertCount( count( $expectedIds ), $resultData['search'] );

		foreach ( $expectedIds as $index => $expectedId ) {
			$this->assertSame( $expectedId, $resultData['search'][$index]['id'] );
		}
	}

	/**
	 * @param EntitySearchHelper $entitySearchTermIndex
	 * @param string $query
	 *
	 * @return array
	 */
	private function executeApiModule( EntitySearchHelper $entitySearchTermIndex, $query ) {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( [
			'language' => 'en',
			'search' => $query,
		] ) );

		$repo = WikibaseRepo::getDefaultInstance();

		$apiModule = new SearchEntities(
			new ApiMain( $context ),
			'',
			$entitySearchTermIndex,
			null,
			new StaticContentLanguages( [ 'en' ] ),
			$repo->getEntitySourceDefinitions(),
			$this->createMock( EntityTitleTextLookup::class ),
			$this->createMock( EntityUrlLookup::class ),
			$this->createMock( EntityArticleIdLookup::class ),
			$this->createMock( ApiErrorReporter::class )
		);

		$apiModule->execute();

		return $apiModule->getResult()->getResultData( null, [ 'Strip' => 'all' ] );
	}

	/**
	 * @return LanguageFallbackChainFactory
	 */
	private function newLanguageFallbackChainFactory() {
		$fallbackChain = $this->getMockBuilder( LanguageFallbackChain::class )
			->setConstructorArgs( [ [] ] )
			->setMethods( [ 'getFetchLanguageCodes' ] )
			->getMock();
		$fallbackChain->expects( $this->any() )
			->method( 'getFetchLanguageCodes' )
			->willReturn( [ 'phpunit_lang' ] );

		$factory = $this->createMock( LanguageFallbackChainFactory::class );
		$factory->method( 'newFromLanguage' )->willReturn( $fallbackChain );
		$factory->method( 'newFromLanguageCode' )->willReturn( $fallbackChain );

		return $factory;
	}

}
