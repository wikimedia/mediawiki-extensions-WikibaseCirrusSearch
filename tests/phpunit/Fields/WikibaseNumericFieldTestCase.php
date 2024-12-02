<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use DummySearchIndexFieldDefinition;
use MediaWikiIntegrationTestCase;
use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\Search\Elastic\Fields\WikibaseNumericField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * Base class for testing numeric fields.
 *
 * @covers \Wikibase\Search\Elastic\Fields\WikibaseNumericField
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
abstract class WikibaseNumericFieldTestCase extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	public function testGetMapping() {
		$field = $this->getFieldObject();
		$searchEngine = $this->createMock( SearchEngine::class );

		$searchEngine->method( 'makeSearchFieldMapping' )
			->willReturnCallback( static function ( $name, $type ) {
				return new DummySearchIndexFieldDefinition( $name, $type );
			} );

		$mapping = $field->getMappingField( $searchEngine, get_class( $field ) )
			->getMapping( $searchEngine );
		$this->assertEquals( \SearchIndexField::INDEX_TYPE_INTEGER, $mapping['type'] );
		$this->assertEquals( get_class( $field ), $mapping['name'] );
	}

	/**
	 * @dataProvider getFieldDataProvider
	 */
	public function testGetFieldData( $expected, EntityDocument $entity ) {
		$labelCountField = $this->getFieldObject();

		$this->assertSame( $expected, $labelCountField->getFieldData( $entity ) );
	}

	abstract public static function getFieldDataProvider();

	/**
	 * @return WikibaseNumericField
	 */
	abstract protected function getFieldObject();

}
