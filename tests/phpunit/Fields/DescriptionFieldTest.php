<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use ExtensionRegistry;
use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * @covers \Wikibase\Search\Elastic\Fields\DescriptionsField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 */
class DescriptionFieldTest extends SearchFieldTestCase {
	use WikibaseSearchTestCase;

	public function getFieldDataProvider() {
		$item = new Item();
		$item->getFingerprint()->setDescription( 'es', 'Gato' );
		$item->getFingerprint()->setDescription( 'ru', 'Кошка' );
		$item->getFingerprint()->setDescription( 'de', 'Katze' );
		$item->getFingerprint()->setDescription( 'fr', 'Chat' );

		$prop = Property::newFromType( 'string' );
		$prop->getFingerprint()->setDescription( 'en', 'astrological sign' );
		$prop->getFingerprint()->setDescription( 'ru', 'знак зодиака' );

		$mock = $this->createMock( EntityDocument::class );

		return [
			'item descriptions' => [
				[
					'es' => [ 'Gato' ],
					'ru' => [ 'Кошка' ],
					'de' => [ 'Katze' ],
					'fr' => [ 'Chat' ],
				],
				$item
			],
			'empty item' => [
				null,
				new Item()
			],
			'property descriptions' => [
				[
					'en' => [ 'astrological sign' ],
					'ru' => [ 'знак зодиака' ],
				],
				$prop
			],
			'empty property' => [
				null,
				Property::newFromType( 'string' )
			],
			'plain entity document' => [ null, $mock ],
		];
	}

	/**
	 * @dataProvider  getFieldDataProvider
	 */
	public function testDescriptions( ?array $expected, EntityDocument $entity ) {
		$labels = new DescriptionsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$this->assertSame( $expected, $labels->getFieldData( $entity ) );
	}

	public function testGetMapping() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
		$labels = new DescriptionsField( [ 'en', 'es', 'ru', 'de' ],
			[
				'en' => [ 'index' => true, 'search' => true ],
				'es' => [ 'index' => true, 'search' => false ],
				'ru' => [ 'index' => false, 'search' => true ],
			]
		);
		$searchEngine = $this->getSearchEngineMock();
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );

		$mapping = $labels->getMapping( $searchEngine );
		$this->assertArrayHasKey( 'properties', $mapping );
		$this->assertCount( 4, $mapping['properties'] );
		$this->assertEquals( 'object', $mapping['type'] );

		$this->assertEquals( "en_text", $mapping['properties']['en']['analyzer'] );
		$this->assertEquals( "es_text_search", $mapping['properties']['es']['search_analyzer'] );
		$this->assertFalse( $mapping['properties']['ru']['index'] );
		$this->assertEquals( "ru_plain",
			$mapping['properties']['ru']['fields']['plain']['analyzer'] );
		$this->assertFalse( $mapping['properties']['de']['index'] );
		$this->assertEquals( "de_plain_search",
			$mapping['properties']['de']['fields']['plain']['search_analyzer'] );
	}

	public function testGetMappingOtherSearchEngine() {
		$labels = new DescriptionsField( [ 'en', 'es', 'ru', 'de' ], [] );

		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );

		$this->assertSame( [], $labels->getMapping( $searchEngine ) );
	}

	public function testHints() {
		$labels = new DescriptionsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$searchEngine = $this->getSearchEngineMock();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->assertEquals( [], $labels->getEngineHints( $searchEngine ) );
		} else {
			$this->assertEquals( [ 'noop' => 'equals' ], $labels->getEngineHints( $searchEngine ) );
		}
	}

}
