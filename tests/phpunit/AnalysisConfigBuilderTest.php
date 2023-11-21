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
		$expectedArgs = [
			[
				[],
				[ 'en', 'ru' ],
				[ 'plain', 'plain_search', 'text', 'text_search' ]
			],
			[
				[],
				[ 'uk', 'he', 'zh' ],
				[ 'plain', 'plain_search' ]
			]
		];
		$upstreamBuilder->expects( $this->exactly( count( $expectedArgs ) ) )
			->method( 'buildLanguageConfigs' )
			->willReturnCallback( function ( $conf, $lang, $analyzers ) use ( &$expectedArgs ) {
				$curExpectedArgs = array_shift( $expectedArgs );
				$this->assertSame( $curExpectedArgs[0], $conf );
				$this->assertEqualsCanonicalizing( $curExpectedArgs[1], $lang );
				$this->assertSame( $curExpectedArgs[2], $analyzers );
				return [];
			} );

		$oldConfig = [];
		$builder = new ConfigBuilder( [ 'en', 'ru', 'uk', 'he', 'zh' ], new \HashConfig( $langSettings ),
				$upstreamBuilder );

		$builder->buildConfig( $oldConfig );
	}

}
