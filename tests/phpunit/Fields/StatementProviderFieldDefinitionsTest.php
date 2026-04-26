<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch\CirrusSearch;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Search\Elastic\Fields\StatementProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\StatementTimeField;

/**
 * @covers \Wikibase\Search\Elastic\Fields\StatementProviderFieldDefinitions
 *
 * @group WikibaseElastic
 * @group WikibaseRepo
 * @group Wikibase
 */
class StatementProviderFieldDefinitionsTest extends TestCase {

	public function testStatementTimeFieldUsesTimePropertiesFromSearchIndexProperties() {
		$fieldDefinitions = new StatementProviderFieldDefinitions(
			new DataTypeFactory( [] ),
			$this->getPropertyTypeLookup( [ 'P571' => 'time' ] ),
			[],
			[ 'P180', 'P571' ],
			[],
			[],
			[],
			null,
			null
		);

		$fields = $fieldDefinitions->getFields();
		$statementTimeField = $fields['statement_time'];
		$this->assertInstanceOf( StatementTimeField::class, $statementTimeField );

		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
				'properties' => [
					'P571' => [
						'type' => 'date',
						'format' => 'date_optional_time',
					],
				],
			],
			$statementTimeField->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testStatementTimeFieldSkipsExcludedProperties() {
		$fieldDefinitions = new StatementProviderFieldDefinitions(
			new DataTypeFactory( [] ),
			$this->getPropertyTypeLookup( [ 'P571' => 'time' ] ),
			[],
			[ 'P571' ],
			[],
			[ 'P571' ],
			[],
			null,
			null
		);

		$fields = $fieldDefinitions->getFields();
		$statementTimeField = $fields['statement_time'];
		$this->assertInstanceOf( StatementTimeField::class, $statementTimeField );

		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
			],
			$statementTimeField->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	private function getPropertyTypeLookup( array $map ): PropertyDataTypeLookup {
		$lookup = $this->createMock( PropertyDataTypeLookup::class );
		$lookup->method( 'getDataTypeIdForProperty' )
			->willReturnCallback( static function ( PropertyId $propertyId ) use ( $map ) {
				return $map[$propertyId->getSerialization()] ?? 'wikibase-item';
			} );
		return $lookup;
	}

}
