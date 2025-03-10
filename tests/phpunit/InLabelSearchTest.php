<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusTestCase;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\InLabelSearch;

/**
 * @covers \Wikibase\Search\Elastic\InLabelSearch
 * @covers \Wikibase\Search\Elastic\Query\InLabelQuery
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class InLabelSearchTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	/**
	 * @dataProvider searchDataProvider
	 */
	public function testSearchElastic( array $params, string $expected ) {
		$this->resetGlobalSearchConfig();

		$this->setMwGlobals( [ 'wgEntitySearchUseCirrus' => true ] );
		$limit = 10;
		if ( isset( $params['limit'] ) ) {
			$limit = $params['limit'];
		}
		$elasticQuery = $this->newEntitySearch()->search( $params['search'], $params['language'], $params['type'], $limit );
		$elasticQuery = $elasticQuery['__main__'] ?? $elasticQuery;
		unset( $elasticQuery['path'] );
		// serialize_precision set for T205958
		$this->setIniSetting( 'serialize_precision', 10 );
		$encodedData = CirrusTestCase::encodeFixture( $elasticQuery );
		$this->assertFileContains( $expected, $encodedData, CirrusTestCase::canRebuildFixture() );
	}

	public static function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/inLabelSearch/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = __DIR__ . "/data/inLabelSearch/$testName.expected";
			$tests[$testName] = [ $query, $expectedFile ];
		}

		return $tests;
	}

	private function newEntitySearch() {
		return new InLabelSearch(
			WikibaseRepo::getLanguageFallbackChainFactory(),
			new BasicEntityIdParser(),
			WikibaseRepo::getContentModelMappings(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests()
		);
	}

	private function resetGlobalSearchConfig() {
		// For whatever reason the mediawiki test suite reuses the same config
		// objects for the entire test. This breaks caches inside the cirrus
		// SearchConfig, so reset them as necessary.
		$config = \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$reflProp = new \ReflectionProperty( $config, 'profileService' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $config, null );
	}

}
