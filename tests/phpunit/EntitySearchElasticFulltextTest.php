<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\BoostTemplatesFeature;
use CirrusSearch\Query\FullTextQueryStringQueryBuilder;
use CirrusSearch\Query\InSourceFeature;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\EntityFullTextQueryBuilder;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\Hooks;

/**
 * @covers \Wikibase\Search\Elastic\EntityFullTextQueryBuilder
 * @covers \Wikibase\Search\Elastic\Hooks::registerSearchProfiles()
 *
 * @group Wikibase
 * @license GPL-2.0-or-later
 * @author  Stas Malyshev
 */
class EntitySearchElasticFulltextTest extends MediaWikiTestCase {
	use WikibaseSearchTestCase;

	/**
	 * @var array search settings for the test
	 */
	private static $ENTITY_SEARCH_CONFIG = [
		'statementBoost' => [ 'P31=Q4167410' => '-10' ],
		'defaultFulltextRescoreProfile' => 'wikibase_prefix_boost',
		'useStemming' => [ 'en' => [ 'query' => true ] ]
	];

	public function setUp() {
		parent::setUp();
		if ( !class_exists( CirrusSearch::class ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
		$this->disableWikibaseNative();
		// Override the profile service hooks so that we can test that the rescore profiles
		// are properly initialized
		parent::setTemporaryHook( 'CirrusSearchProfileService', function ( SearchProfileService $service ) {
			Hooks::registerSearchProfiles( $service, self::$ENTITY_SEARCH_CONFIG );
		} );
	}

	public function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/entityFulltext/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, - 6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = "$testName-es" . EntitySearchElastic::getExpectedElasticMajorVersion() . '.expected';
			$tests[$testName] = [ $query, __DIR__ . '/data/entityFulltext/' . $expectedFile ];
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
	 * @param string[] $params
	 * @param string $expected
	 */
	public function testSearchElastic( $params, $expected ) {
		$this->setMwGlobals( [
			'wgCirrusSearchQueryStringMaxDeterminizedStates' => 500,
			'wgCirrusSearchElasticQuirks' => [],
		] );

		$config = new SearchConfig();

		$builder = new EntityFullTextQueryBuilder(
			self::$ENTITY_SEARCH_CONFIG,
			$this->getConfigSettings(),
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			$params['userLang']
		);

		$features = [
			new InSourceFeature( $config ),
			new BoostTemplatesFeature(),
		];
		$builderSettings = $config->getProfileService()
					   ->loadProfileByName( SearchProfileService::FT_QUERY_BUILDER, 'default' );
		$defaultBuilder = new FullTextQueryStringQueryBuilder( $config, $features, $builderSettings['settings'] );

		$context = new SearchContext( $config, $params['ns'] );
		$defaultBuilder->build( $context, $params['search'] );
		$builder->build( $context, $params['search'] );
		$query = $context->getQuery();
		$rescore = $context->getRescore();

		// serialize_precision set for T205958
		$this->setIniSetting( 'serialize_precision', 10 );
		$encoded = json_encode( [ 'query' => $query->toArray(), 'rescore_query' => $rescore ],
			JSON_PRETTY_PRINT );
		$this->assertFileContains( $expected, $encoded );
	}

	/**
	 * Check that the search does not do anything if results are not possible
	 * or if advanced syntax is used.
	 */
	public function testSearchFallback() {
		$builder = new EntityFullTextQueryBuilder(
			[],
			[],
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			'en'
		);

		$context = new SearchContext( new SearchConfig(), [ 150 ] );
		$context->setResultsPossible( false );

		$builder->build( $context, "test" );
		$this->assertNotContains( 'entity_full_text', $context->getSyntaxUsed() );

		$context->setResultsPossible( true );
		$context->addSyntaxUsed( 'regex' );

		$builder->build( $context, "test" );
		$this->assertNotContains( 'entity_full_text', $context->getSyntaxUsed() );
	}

	public function testPhraseRescore() {
		$this->setMwGlobals( [
			'wgCirrusSearchWikimediaExtraPlugin' => [ 'token_count_router' => true ],
		] );

		$config = new SearchConfig();

		$builder = new EntityFullTextQueryBuilder(
			self::$ENTITY_SEARCH_CONFIG,
			$this->getConfigSettings(),
			new LanguageFallbackChainFactory(),
			new ItemIdParser(),
			'en'
		);

		$features = [
			new InSourceFeature( $config ),
			new BoostTemplatesFeature(),
		];
		$builderSettings = $config->getProfileService()
			->loadProfileByName( SearchProfileService::FT_QUERY_BUILDER, 'default' );
		$defaultBuilder = new FullTextQueryStringQueryBuilder( $config, $features, $builderSettings['settings'] );

		$context = new SearchContext( $config, [ 0 ] );
		$defaultBuilder->build( $context, 'test' );
		$builder->build( $context, 'test' );
		$this->assertFileContains( __DIR__ . '/data/entityFulltext/phraseRescore.expected',
			json_encode( $context->getPhraseRescoreQuery()->toArray(), JSON_PRETTY_PRINT ) );
	}

}
