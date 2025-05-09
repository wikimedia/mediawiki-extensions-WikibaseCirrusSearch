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
 * @covers \Wikibase\Search\Elastic\Fields\LabelsField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class LabelsFieldTest extends SearchFieldTestCase {
	use WikibaseSearchTestCase;

	public static function getFieldDataProvider() {
		$item = new Item();
		$item->getFingerprint()->setLabel( 'es', 'Gato' );
		$item->getFingerprint()->setLabel( 'ru', 'Кошка' );
		$item->getFingerprint()->setLabel( 'de', 'Katze' );
		$item->getFingerprint()->setLabel( 'fr', 'Chat' );

		$prop = Property::newFromType( 'string' );
		$prop->getFingerprint()->setLabel( 'en', 'astrological sign' );
		$prop->getFingerprint()->setLabel( 'ru', 'знак зодиака' );
		$prop->getFingerprint()->setAliasGroup( 'en', [ 'zodiac sign' ] );
		$prop->getFingerprint()->setAliasGroup( 'es', [ 'signo zodiacal' ] );

		yield 'item labels' => [
			[
				'es' => [ 'Gato' ],
				'ru' => [ 'Кошка' ],
				'de' => [ 'Katze' ],
				'fr' => [ 'Chat' ]
			],
			$item,
			true,
		];
		yield 'empty item' => [ null, new Item(), true ];
		yield 'property labels' => [
			[
				'en' => [ 'astrological sign', 'zodiac sign' ],
				'ru' => [ 'знак зодиака' ],
				'es' => [ '', 'signo zodiacal' ],
			],
			$prop,
			true,
		];
		yield 'empty property' => [ null, Property::newFromType( 'string' ), true ];
		yield 'empty entity document' => [ null, null, false ];
	}

	/**
	 * @dataProvider  getFieldDataProvider
	 */
	public function testLabels( ?array $expected, ?EntityDocument $entity, bool $labelsProvider ) {
		$entity ??= $this->createMock( EntityDocument::class );
		$labels = new LabelsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$this->assertSame( $expected, $labels->getFieldData( $entity ) );
		if ( $labelsProvider ) {
			$this->assertSame( $expected, $labels->getLabelsIndexedData( $entity ) );
		}
	}

	public function testGetMapping() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$labels = new LabelsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$searchEngine = $this->getSearchEngineMock();
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );

		$mapping = $labels->getMapping( $searchEngine );
		$this->assertArrayHasKey( 'properties', $mapping );
		$this->assertCount( 4, $mapping['properties'] );
		$this->assertEquals( 'object', $mapping['type'] );
	}

	public function testGetMappingOtherSearchEngine() {
		$labels = new LabelsField( [ 'en', 'es', 'ru', 'de' ], [] );

		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->expects( $this->never() )->method( 'makeSearchFieldMapping' );

		$this->assertSame( [], $labels->getMapping( $searchEngine ) );
	}

	public function testHints() {
		$labels = new LabelsField( [ 'en', 'es', 'ru', 'de' ], [] );
		$searchEngine = $this->getSearchEngineMock();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->assertEquals( [], $labels->getEngineHints( $searchEngine ) );
		} else {
			$this->assertEquals( [ 'noop' => 'equals' ], $labels->getEngineHints( $searchEngine ) );
		}
	}

	/** @dataProvider provideMerge */
	public function testMerge( LabelsField $sut, TermIndexField $that, $expected ): void {
		$actual = $sut->merge( $that );

		$this->assertEquals( $expected, $actual );
	}

	public static function provideMerge(): iterable {
		$sutLanguages = [ 'en', 'de' ];
		$sutStemmingSettings = [ 'stemming' => 'settings' ];
		$sut = new LabelsField( $sutLanguages, $sutStemmingSettings );

		yield 'same field' => [
			'sut' => $sut,
			'that' => $sut,
			'expected' => $sut,
		];

		yield 'equal field' => [
			'sut' => $sut,
			'that' => new LabelsField( $sutLanguages, $sutStemmingSettings ),
			'expected' => $sut,
		];

		yield 'superset of languages' => [
			'sut' => $sut,
			'that' => new LabelsField( [ 'en' /* no 'de' */ ], $sutStemmingSettings ),
			'expected' => $sut,
		];

		yield 'subset of languages' => [
			'sut' => $sut,
			'that' => new LabelsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
			'expected' => new LabelsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
		];

		yield 'intersecting languages' => [
			'sut' => $sut,
			'that' => new LabelsField( [ 'en', /* no 'de' */ 'pt' ], $sutStemmingSettings ),
			'expected' => new LabelsField( [ 'en', 'de', 'pt' ], $sutStemmingSettings ),
		];

		yield 'different stemming settings' => [
			'sut' => $sut,
			'that' => new LabelsField( $sutLanguages, [ 'other' => 'stemmingSettings' ] ),
			'expected' => false,
		];

		yield 'different type' => [
			'sut' => $sut,
			'that' => new DescriptionsField( $sutLanguages, $sutStemmingSettings ),
			'expected' => false,
		];
	}

}
