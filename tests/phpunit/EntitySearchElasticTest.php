<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusTestCase;
use MediaWiki\Language\Language;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\EntitySearchElastic;

/**
 * @covers \Wikibase\Search\Elastic\EntitySearchElastic
 * @covers \Wikibase\Search\Elastic\Query\LabelsCompletionQuery
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
			new FauxRequest(),
			CirrusDebugOptions::forDumpingQueriesInUnitTests()
		);
	}

	public static function searchDataProvider() {
		$tests = [];
		foreach ( glob( __DIR__ . '/data/entitySearch/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$query = json_decode( file_get_contents( $queryFile ), true );
			$expectedFile = __DIR__ . "/data/entitySearch/$testName.expected";
			$tests[$testName] = [ $query, $expectedFile ];
		}

		return $tests;
	}

	private function overrideProfiles( array $profiles ) {
		$this->setTemporaryHook(
			'CirrusSearchProfileService',
			static function ( $service ) use ( $profiles ) {
				foreach ( $profiles as $repoType => $contextProfiles ) {
					$service->registerArrayRepository( $repoType, 'phpunit_config', $contextProfiles );
				}
			},
			/* $replace = */false
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
		$reflProp->setValue( $config, null );
	}

	/**
	 * @dataProvider searchDataProvider
	 * @param string[] $params query parameters
	 * @param string $expected
	 */
	public function testSearchElastic( $params, $expected ) {
		$this->resetGlobalSearchConfig();
		if ( isset( $params['profiles'] ) ) {
			$this->overrideProfiles( $params['profiles'] );
		}

		$this->setMwGlobals( [ 'wgEntitySearchUseCirrus' => true ] );
		$search = $this->newEntitySearch( $this->getServiceContainer()->getLanguageFactory()->getLanguage( $params['userLang'] ) );
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
