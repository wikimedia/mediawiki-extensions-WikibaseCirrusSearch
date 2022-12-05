<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch\CirrusSearch;
use ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * @covers \Wikibase\Search\Elastic\Fields\AllLabelsField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class AllLabelsFieldTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	public function provideFieldData() {
		$item = new Item();
		$item->getFingerprint()->setLabel( 'es', 'Gato' );

		$prop = Property::newFromType( 'string' );
		$prop->getFingerprint()->setLabel( 'en', 'astrological sign' );

		$mock = $this->createMock( EntityDocument::class );

		return [
			[ $item ],
			[ $prop ],
			[ $mock ]
		];
	}

	/**
	 * @dataProvider provideFieldData
	 */
	public function testGetFieldData( EntityDocument $entity ) {
		$labels = new AllLabelsField();
		$this->assertNull( $labels->getFieldData( $entity ) );
	}

	public function testGetMapping() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}

		$labels = new AllLabelsField();

		$searchEngine = $this->createMock( CirrusSearch::class );
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );
		$config = new \CirrusSearch\SearchConfig();
		$searchEngine->method( 'getConfig' )
			->willReturn( $config );

		$mapping = $labels->getMapping( $searchEngine );
		$this->assertArrayHasKey( 'fields', $mapping );
		$this->assertCount( 3, $mapping['fields'] );
		$this->assertEquals( 'text', $mapping['type'] );
		$this->assertFalse( $mapping['index'] );
	}

	public function testGetMappingOtherSearchEngine() {
		$labels = new AllLabelsField();

		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );

		$this->assertSame( [], $labels->getMapping( $searchEngine ) );
	}

}
