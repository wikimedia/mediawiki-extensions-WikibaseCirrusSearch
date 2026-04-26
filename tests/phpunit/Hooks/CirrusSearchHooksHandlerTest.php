<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Tests\Hooks;

use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use MediaWikiIntegrationTestCase;
use Wikibase\Search\Elastic\Hooks\CirrusSearchHooksHandler;
use Wikibase\Search\Elastic\Query\WbStatementTimeFeature;

/**
 * @covers \Wikibase\Search\Elastic\Hooks\CirrusSearchHooksHandler::onCirrusSearchAddQueryFeatures
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class CirrusSearchHooksHandlerTest extends MediaWikiIntegrationTestCase {

	public function testAddsStatementTimeFeature(): void {
		$this->overrideConfigValue( 'WBCSUseCirrus', true );
		$this->overrideConfigValue( 'WBCSAllowedStatementTimeProperties', [ 'P571' ] );
		$features = [];

		$handler = new CirrusSearchHooksHandler();
		$handler->onCirrusSearchAddQueryFeatures(
			$this->createMock( SearchConfig::class ),
			$features
		);

		$statementTimeFeatures = array_values( array_filter(
			$features,
			static fn ( $feature ) => $feature instanceof WbStatementTimeFeature
		) );
		$this->assertCount( 1, $statementTimeFeatures );

		$parsed = $statementTimeFeatures[0]->parseValue(
			'wbstatementtime',
			'P571>=2020-01-01',
			'',
			'',
			'',
			$this->createNoOpMock( WarningCollector::class )
		);
		$this->assertCount( 1, $parsed['clauses'] );
		$this->assertSame( 'P571', $parsed['clauses'][0]['propertyId'] );
	}
}
