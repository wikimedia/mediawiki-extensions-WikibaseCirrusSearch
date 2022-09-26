<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusTestCase;
use Language;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\EntitySearchElastic;

/**
 * @covers \Wikibase\Search\Elastic\EntitySearchElastic
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class EntitySearchElasticTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	/**
	 * @param Language $userLang
	 * @return EntitySearchElastic
	 */
	private function newEntitySearch( Language $userLang ) {
		return new EntitySearchElastic(
			WikibaseRepo::getLanguageFallbackChainFactory(),
			new BasicEntityIdParser(),
			$userLang,
			WikibaseRepo::getContentModelMappings(),
			new \FauxRequest(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests( false )
		);
	}

	public function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/entitySearch/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = __DIR__ . "/data/entitySearch/$testName.expected";
			$tests[$testName] = [ $query, $expectedFile ];
		}

		return $tests;
	}

	/**
	 * @dataProvider searchDataProvider
	 * @param string[] $params query parameters
	 * @param string $expected
	 */
	public function testSearchElastic( $params, $expected ) {
		$this->setMwGlobals( [ 'wgEntitySearchUseCirrus' => true ] );
		$search = $this->newEntitySearch( Language::factory( $params['userLang'] ) );
		$limit = 10;
		if ( isset( $params['limit'] ) ) {
			$limit = $params['limit'];
		}
		$elasticQuery = $search->getRankedSearchResults(
			$params['search'], $params['language'],
			$params['type'], $limit, $params['strictlanguage']
		);
		$elasticQuery = $elasticQuery['__main__'] ?? $elasticQuery;
		unset( $elasticQuery['path'] );
		// serialize_precision set for T205958
		$this->setIniSetting( 'serialize_precision', 10 );
		$encodedData = CirrusTestCase::encodeFixture( $elasticQuery );
		$this->assertFileContains( $expected, $encodedData, CirrusTestCase::canRebuildFixture() );
	}

}
