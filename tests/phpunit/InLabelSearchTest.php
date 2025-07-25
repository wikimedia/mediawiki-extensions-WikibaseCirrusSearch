<?php declare( strict_types = 1 );

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
 * @covers \Wikibase\Search\Elastic\Query\InLabelFilterVisitor
 * @covers \Wikibase\Search\Elastic\Query\InLabelScoringVisitor
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
	public function testSearchElastic( array $params, string $expected ): void {
		$this->resetGlobalSearchConfig();

		$this->setMwGlobals( [ 'wgEntitySearchUseCirrus' => true ] );
		$limit = 10;
		$offset = 0;
		if ( isset( $params['limit'] ) ) {
			$limit = $params['limit'];
		}
		if ( isset( $params['offset'] ) ) {
			$offset = $params['offset'];
		}
		$elasticQuery = $this->newEntitySearch( $params['stemming'] ?? [] )
			->search( $params['search'], $params['language'], $params['type'], $limit, $offset );
		$elasticQuery = $elasticQuery['__main__'] ?? $elasticQuery;
		unset( $elasticQuery['path'] );
		// serialize_precision set for T205958
		$this->setIniSetting( 'serialize_precision', 10 );
		$encodedData = CirrusTestCase::encodeFixture( $elasticQuery );
		$this->assertFileContains( $expected, $encodedData, CirrusTestCase::canRebuildFixture() );
	}

	public static function searchDataProvider(): array {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/inLabelSearch/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = __DIR__ . "/data/inLabelSearch/$testName.expected";
			$tests[$testName] = [ $query, $expectedFile ];
		}

		return $tests;
	}

	private function newEntitySearch( array $stemmingSettings ): InLabelSearch {
		return new InLabelSearch(
			WikibaseRepo::getLanguageFallbackChainFactory(),
			new BasicEntityIdParser(),
			WikibaseRepo::getContentModelMappings(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests(),
			$stemmingSettings
		);
	}

	private function resetGlobalSearchConfig(): void {
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
