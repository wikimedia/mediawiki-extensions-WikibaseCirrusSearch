<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusSearch;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\EntityFullTextQueryBuilder;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\Hooks;
use Wikibase\Search\Elastic\WikibaseSearchConfig;

/**
 * @covers \Wikibase\Search\Elastic\EntityFullTextQueryBuilder
 * @covers \Wikibase\Search\Elastic\Hooks::registerSearchProfiles()
 *
 * @group Wikibase
 * @license GPL-2.0-or-later
 * @author  Stas Malyshev
 */
class EntitySearchElasticFulltextTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var array search settings for the test
	 */
	private static $ENTITY_SEARCH_CONFIG = [
		'wgWBCSUseCirrus' => true,
		'wgWBCSStatementBoost' => [ 'P31=Q4167410' => '-10' ],
		'wgWBCSDefaultFulltextRescoreProfile' => 'wikibase_prefix_boost',
		'wgWBCSUseStemming' => [ 'en' => [ 'query' => true ] ]
	];
	private static $FULLTEXT_SEARCH_TYPES = [
		// Mimic wikidata.org for the tests (items on NS_MAIN, properties on 120)
		EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT => [ NS_MAIN, 120 ]
	];

	protected function setUp(): void {
		parent::setUp();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
		$this->setMwGlobals( self::$ENTITY_SEARCH_CONFIG );
		// Override the profile service hooks so that we can test that the rescore profiles
		// are properly initialized
		parent::setTemporaryHook( 'CirrusSearchProfileService',
			static function ( SearchProfileService $service ) {
				Hooks::registerSearchProfiles(
					$service,
					new WikibaseSearchConfig( self::$ENTITY_SEARCH_CONFIG ),
					self::$FULLTEXT_SEARCH_TYPES
				);
			}
		);
	}

	public function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/entityFulltext/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = "$testName.expected";
			$tests[$testName] = [ $query, __DIR__ . '/data/entityFulltext/' . $expectedFile ];
		}

		foreach ( glob( __DIR__ . '/data/entityFulltextIgnored/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$tests[$testName] = [ $query, false ];
		}

		return $tests;
	}

	private function getConfigSettings() {
		return [
			'any'               => 0.04,
			'lang-exact'        => 0.78,
			'lang-folded'       => 0.01,
			'lang-partial'      => 0.07,
			'fallback-exact'    => 0.38,
			'fallback-folded'   => 0.005,
			'fallback-partial'  => 0.03,
			'fallback-discount' => 0.1,
			'phrase' => [
				'all' => 0.001,
				'all.plain' => 0.1,
				'slop' => 0,
			],
		];
	}

	/**
	 * @dataProvider searchDataProvider
	 */
	public function testSearchElastic( $params, $expected ) {
		$this->markTestSkipped( "Temporarily disabled" );
		$this->setMwGlobals( [
			'wgCirrusSearchQueryStringMaxDeterminizedStates' => 500,
			'wgCirrusSearchElasticQuirks' => [],
			'wgCirrusSearchFullTextQueryBuilderProfile' => 'default',
			'wgLang' => \Language::factory( $params['userLang'] ),
		] );

		$config = new SearchConfig();
		$cirrus = new CirrusSearch( $config, CirrusDebugOptions::forDumpingQueriesInUnitTests() );
		$cirrus->setNamespaces( $params['ns'] );
		$result = json_decode( $cirrus->searchText( $params['search'] )->getValue(), true );
		if ( $expected === false ) {
			$this->assertStringStartsNotWith( EntityFullTextQueryBuilder::ENTITY_FULL_TEXT_MARKER, $result['__main__']['description'] );
			return;
		}
		$this->assertStringStartsWith( EntityFullTextQueryBuilder::ENTITY_FULL_TEXT_MARKER, $result['__main__']['description'] );
		$actual = CirrusTestCase::encodeFixture( [
			'query' => $result['__main__']['query']['query'],
			'rescore_query' => $result['__main__']['query']['rescore'],
		] );

		$this->assertFileContains( $expected, $actual, CirrusTestCase::canRebuildFixture() );
	}

	public function testPhraseRescore() {
		$this->setMwGlobals( [
			'wgCirrusSearchWikimediaExtraPlugin' => [ 'token_count_router' => true ],
		] );

		$config = new SearchConfig();

		$builder = new EntityFullTextQueryBuilder(
			self::$ENTITY_SEARCH_CONFIG['wgWBCSUseStemming'],
			$this->getConfigSettings(),
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			'en'
		);

		$context = new SearchContext( $config, [ 0 ] );
		$builder->build( $context, 'test' );
		$this->assertFileContains( __DIR__ . '/data/entityFulltext/phraseRescore.expected',
			json_encode( $context->getPhraseRescoreQuery()->toArray(), JSON_PRETTY_PRINT ) );
	}

}
