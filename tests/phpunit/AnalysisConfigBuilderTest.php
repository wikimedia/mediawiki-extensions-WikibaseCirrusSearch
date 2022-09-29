<?php

namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use MediaWikiIntegrationTestCase;
use Wikibase\Search\Elastic\ConfigBuilder;

/**
 * @group Wikibase
 * @covers \Wikibase\Search\Elastic\ConfigBuilder
 */
class AnalysisConfigBuilderTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	public function testAnalysisConfig() {
		$langSettings = [];
		$langSettings['UseStemming'] = [
			'en' => [ 'index' => true, 'query' => true ],
			'ru' => [ 'index' => true, 'query' => false ],
			'uk' => [ 'index' => false, 'query' => true ],
			'he' => [ 'index' => false, 'query' => false ],
		];
		$upstreamBuilder = $this->createMock( AnalysisConfigBuilder::class );
		// Temporary workaround for the PHPUnit 9 update
		$equalToCanonicalizing = function ( $val ) {
			return method_exists( $this, 'equalToCanonicalizing' )
				? $this->equalToCanonicalizing( $val )
				: $this->equalTo( $val, 0, 10, true );
		};
		$upstreamBuilder->expects( $this->exactly( 2 ) )
			->method( 'buildLanguageConfigs' )
			->withConsecutive(
				[
					$this->equalTo( [] ),
					$equalToCanonicalizing( [ 'en', 'ru' ] ),
					$this->equalTo( [ 'plain', 'plain_search', 'text', 'text_search' ] )
				], [
					$this->equalTo( [] ),
				$equalToCanonicalizing( [ 'uk', 'he', 'zh' ] ),
					$this->equalTo( [ 'plain', 'plain_search' ] )
				] )
			->willReturn( [] );

		$oldConfig = [];
		$builder = new ConfigBuilder( [ 'en', 'ru', 'uk', 'he', 'zh' ], new \HashConfig( $langSettings ),
				$upstreamBuilder );

		$builder->buildConfig( $oldConfig );
	}

}
