<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use MediaWiki\Registration\ExtensionRegistry;
use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Fields\LabelsField;
use Wikibase\Search\Elastic\Fields\TermIndexField;
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

	public static function getFieldDataProvider() {
		$item = new Item();
		$item->getFingerprint()->setDescription( 'es', 'Gato' );
		$item->getFingerprint()->setDescription( 'ru', 'Кошка' );
		$item->getFingerprint()->setDescription( 'de', 'Katze' );
		$item->getFingerprint()->setDescription( 'fr', 'Chat' );

		$prop = Property::newFromType( 'string' );
		$prop->getFingerprint()->setDescription( 'en', 'astrological sign' );
		$prop->getFingerprint()->setDescription( 'ru', 'знак зодиака' );

		yield 'item descriptions' => [
			[
				'es' => [ 'Gato' ],
				'ru' => [ 'Кошка' ],
				'de' => [ 'Katze' ],
				'fr' => [ 'Chat' ],
			],
			$item,
			true,
		];
		yield 'empty item' => [
			null,
			new Item(),
			true,
		];
		yield 'property descriptions' => [
			[
				'en' => [ 'astrological sign' ],
				'ru' => [ 'знак зодиака' ],
			],
			$prop,
			true,
		];
		yield 'empty property' => [
			null,
			Property::newFromType( 'string' ),
			true,
		];
		yield 'plain entity document' => [ null, null, false ];
	}

	/**
	 * @dataProvider  getFieldDataProvider
	 */
	public function testDescriptions( ?array $expected, ?EntityDocument $entity, bool $descriptionsProvider ) {
		$entity ??= $this->createMock( EntityDocument::class );
		$labels = new DescriptionsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$this->assertSame( $expected, $labels->getFieldData( $entity ) );
		if ( $descriptionsProvider ) {
			$this->assertSame( $expected, $labels->getDescriptionsIndexedData( $entity ) );
		}
	}

	public function testGetMapping() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
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

	/** @dataProvider provideMerge */
	public function testMerge( DescriptionsField $sut, TermIndexField $that, $expected ): void {
		$actual = $sut->merge( $that );

		$this->assertEquals( $expected, $actual );
	}

	public static function provideMerge(): iterable {
		$sutLanguages = [ 'en', 'de' ];
		$sutStemmingSettings = [ 'stemming' => 'settings' ];
		$sut = new DescriptionsField( $sutLanguages, $sutStemmingSettings );

		yield 'same field' => [
			'sut' => $sut,
			'that' => $sut,
			'expected' => $sut,
		];

		yield 'equal field' => [
			'sut' => $sut,
			'that' => new DescriptionsField( $sutLanguages, $sutStemmingSettings ),
			'expected' => $sut,
		];

		yield 'superset of languages' => [
			'sut' => $sut,
			'that' => new DescriptionsField( [ 'en' /* no 'de' */ ], $sutStemmingSettings ),
			'expected' => $sut,
		];

		yield 'subset of languages' => [
			'sut' => $sut,
			'that' => new DescriptionsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
			'expected' => new DescriptionsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
		];

		yield 'intersecting languages' => [
			'sut' => $sut,
			'that' => new DescriptionsField( [ 'en', /* no 'de' */ 'pt' ], $sutStemmingSettings ),
			'expected' => new DescriptionsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
		];

		yield 'compatible stemming settings (one side empty)' => [
			'sut' => $sut,
			'that' => new DescriptionsField( $sutLanguages, [] ),
			'expected' => $sut,
		];

		yield 'different stemming settings' => [
			'sut' => $sut,
			'that' => new DescriptionsField( $sutLanguages, [ 'other' => 'stemmingSettings' ] ),
			'expected' => false,
		];

		yield 'different type' => [
			'sut' => $sut,
			'that' => new LabelsField( $sutLanguages, $sutStemmingSettings ),
			'expected' => false,
		];
	}

}
