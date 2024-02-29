<?php

namespace Wikibase\Search\Elastic\Tests;

use ApiTestCase;
use CirrusSearch\CirrusDebugOptions;
use Language;
use MediaWiki\Request\FauxRequest;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;
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
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$mockEntitySearchElastic = $this->getMockBuilder( EntitySearchElastic::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'getRankedSearchResults' ] )
				->getMock();

		$mockEntitySearchElastic->method( 'getRankedSearchResults' )
			->willReturnCallback( $this->makeElasticSearchCallback() );

		$this->setService( 'WikibaseRepo.EntitySearchHelper', $mockEntitySearchElastic );

		[ $resultData ] = $this->doApiRequest( [
			'action' => 'wbsearchentities',
			'language' => 'en',
			'search' => $query,
		] );
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
			// Transitional, query dumps will always be wrapped in an array

			$result = $result['__main__'] ?? $result;
			// FIXME: this is very brittle, but I don't know how to make it better.
			$matchId = $result['query']['query']['bool']['should'][1]['term']['title.keyword'];
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
			$this->createMock( Language::class ),
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
