<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Search\CirrusIndexField;
use DataValues\StringValue;
use DataValues\TimeValue;
use MediaWiki\Search\SearchEngine;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Repo\Tests\ChangeOp\StatementListProviderDummy;
use Wikibase\Search\Elastic\Fields\StatementTimeField;

/**
 * @covers \Wikibase\Search\Elastic\Fields\StatementTimeField
 */
class StatementTimeFieldTest extends TestCase {

	private function getPropertyTypeLookup( array $map ): PropertyDataTypeLookup {
		$lookup = $this->createMock( PropertyDataTypeLookup::class );
		$lookup->method( 'getDataTypeIdForProperty' )->willReturnCallback(
			static fn ( PropertyId $id ) => $map[$id->getSerialization()] ?? 'unknown'
		);
		return $lookup;
	}

	private function newField(
		PropertyDataTypeLookup $lookup,
		?LoggerInterface $logger = null,
		?callable $statementProvider = null
	): StatementTimeField {
		return new class(
			new DataTypeFactory( [] ),
			$lookup,
			[ 'P1' ],
			[],
			[],
			[],
			$logger,
			$statementProvider
		) extends StatementTimeField {
			public const NAME = 'test_statement_time';
		};
	}

	public function testGetFieldData() {
		$entity = new StatementListProviderDummy( 'Q1' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2015-11-11T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2016-12-13T14:15:16Z',
				0,
				0,
				0,
				TimeValue::PRECISION_SECOND,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+00000002013-07-16T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'-00000002013-07-16T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+00000010000-01-01T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+1712-02-30T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2014-01-01T00:00:00Z',
				0,
				0,
				0,
				10,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 2, new TimeValue(
				'+2017-01-01T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 3, new StringValue( 'not-a-time' ) )
		);

		$field = $this->newField( $this->getPropertyTypeLookup( [
			'P1' => 'time',
			'P2' => 'time',
			'P3' => 'string',
		] ) );

		$this->assertSame(
			[
				'P1' => [
					'2015-11-11',
					'2016-12-13T14:15:16Z',
					'2013-07-16',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testGetFieldDataNormalizesPaddedYearFromTimeValue() {
		$entity = new StatementListProviderDummy( 'Q1' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+00000002013-07-16T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);

		$field = $this->newField( $this->getPropertyTypeLookup( [ 'P1' => 'time' ] ) );

		$this->assertSame(
			[
				'P1' => [
					'2013-07-16',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testGetFieldDataSkipsLeapSeconds() {
		$entity = new StatementListProviderDummy( 'Q1' );
		foreach ( [ 59, 60, 61 ] as $second ) {
			$entity->getStatements()->addNewStatement(
				new PropertyValueSnak( 1, new TimeValue(
					"+2016-12-31T23:59:$second" . 'Z',
					0,
					0,
					0,
					TimeValue::PRECISION_SECOND,
					TimeValue::CALENDAR_GREGORIAN
				) )
			);
		}

		$field = $this->newField( $this->getPropertyTypeLookup( [ 'P1' => 'time' ] ) );

		$this->assertSame(
			[
				'P1' => [
					'2016-12-31T23:59:59Z',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testGetFieldDataSkipsZeroMonthOrDayTimestamps() {
		$entity = new StatementListProviderDummy( 'Q1' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2024-00-00T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_YEAR,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2024-00-00T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2024-05-00T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);

		$field = $this->newField( $this->getPropertyTypeLookup( [ 'P1' => 'time' ] ) );

		$this->assertSame( [], $field->getFieldData( $entity ) );
	}

	public function testGetFieldDataIgnoresTimezoneOffsets() {
		$entity = new StatementListProviderDummy( 'Q1' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2026-06-22T00:00:00Z',
				60,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2026-06-22T23:30:00Z',
				-300,
				0,
				0,
				TimeValue::PRECISION_SECOND,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);

		$field = $this->newField( $this->getPropertyTypeLookup( [ 'P1' => 'time' ] ) );

		$this->assertSame(
			[
				'P1' => [
					'2026-06-22',
					'2026-06-22T23:30:00Z',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testGetFieldDataWithStatementProvider() {
		$entity = $this->createMock( EntityDocument::class );
		$statementProvider = static function ( EntityDocument $entity ) {
			return [
				new Statement( new PropertyValueSnak( 1, new TimeValue(
					'+2020-05-06T00:00:00Z',
					0,
					0,
					0,
					TimeValue::PRECISION_DAY,
					TimeValue::CALENDAR_GREGORIAN
				) ) )
			];
		};

		$field = $this->newField(
			$this->getPropertyTypeLookup( [ 'P1' => 'time' ] ),
			null,
			$statementProvider
		);

		$this->assertSame(
			[
				'P1' => [
					'2020-05-06',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testSkipsNonGregorianTimeValues() {
		$entity = new StatementListProviderDummy( 'Q2' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+1582-10-04T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				'http://example.org/calendar/julian'
			) )
		);

		$field = $this->newField( $this->getPropertyTypeLookup( [
			'P1' => 'time',
		] ) );

		$this->assertSame( [], $field->getFieldData( $entity ) );
	}

	public function testGetFieldDataUsesPreferredStatementsWhenPresent() {
		$entity = new StatementListProviderDummy( 'Q3' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2020-01-01T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$preferredStatement = new Statement( new PropertyValueSnak( 1, new TimeValue(
			'+2021-01-01T00:00:00Z',
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		) ) );
		$preferredStatement->setRank( Statement::RANK_PREFERRED );
		$entity->getStatements()->addStatement( $preferredStatement );

		$field = $this->newField( $this->getPropertyTypeLookup( [
			'P1' => 'time',
		] ) );

		$this->assertSame(
			[
				'P1' => [
					'2021-01-01',
				],
			],
			$field->getFieldData( $entity )
		);
	}

	public function testGetFieldDataDoesNotFallBackToNormalStatementsWhenPreferredCannotBeIndexed() {
		$entity = new StatementListProviderDummy( 'Q4' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new TimeValue(
				'+2020-01-01T00:00:00Z',
				0,
				0,
				0,
				TimeValue::PRECISION_DAY,
				TimeValue::CALENDAR_GREGORIAN
			) )
		);
		$preferredStatement = new Statement( new PropertyValueSnak( 1, new StringValue( 'not-a-time' ) ) );
		$preferredStatement->setRank( Statement::RANK_PREFERRED );
		$entity->getStatements()->addStatement( $preferredStatement );

		$field = $this->newField( $this->getPropertyTypeLookup( [
			'P1' => 'time',
		] ) );

		$this->assertSame( [], $field->getFieldData( $entity ) );
	}

	public function testGetFieldDataSkipsDeprecatedStatements() {
		$entity = new StatementListProviderDummy( 'Q4' );
		$statement = new Statement( new PropertyValueSnak( 1, new TimeValue(
			'+2020-01-01T00:00:00Z',
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		) ) );
		$statement->setRank( Statement::RANK_DEPRECATED );
		$entity->getStatements()->addStatement( $statement );

		$field = $this->newField( $this->getPropertyTypeLookup( [
			'P1' => 'time',
		] ) );

		$this->assertSame( [], $field->getFieldData( $entity ) );
	}

	public function testSkipsNonTimeProperty() {
		$entity = new StatementListProviderDummy( 'Q3' );
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new StringValue( 'not-a-time' ) )
		);
		$entity->getStatements()->addNewStatement(
			new PropertyValueSnak( 1, new StringValue( 'still-not-a-time' ) )
		);

		$field = $this->newField(
			$this->getPropertyTypeLookup( [ 'P1' => 'string' ] )
		);

		$this->assertSame( [], $field->getFieldData( $entity ) );
	}

	public function testGetMapping() {
		$field = $this->newField( $this->getPropertyTypeLookup( [ 'P1' => 'time' ] ) );
		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
				'properties' => [
					'P1' => [
						'type' => 'date',
						'format' => 'date_optional_time',
					],
				],
			],
			$field->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testGetMappingWithoutConfiguredProperties() {
		$field = new class(
			new DataTypeFactory( [] ),
			$this->getPropertyTypeLookup( [] ),
			[],
			[],
			[],
			[]
		) extends StatementTimeField {
			public const NAME = 'test_statement_time';
		};

		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
			],
			$field->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testGetMappingSkipsMalformedPropertyIds() {
		$field = new class(
			new DataTypeFactory( [] ),
			$this->getPropertyTypeLookup( [ 'P1' => 'time' ] ),
			[ 'P1', 'Q42' ],
			[],
			[],
			[]
		) extends StatementTimeField {
			public const NAME = 'test_statement_time';
		};

		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
				'properties' => [
					'P1' => [
						'type' => 'date',
						'format' => 'date_optional_time',
					],
				],
			],
			$field->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testGetMappingWarnsWhenConfiguredPropertyCannotBeFound() {
		$lookupException = new PropertyDataTypeLookupException( new NumericPropertyId( 'P404' ) );
		$lookup = $this->createMock( PropertyDataTypeLookup::class );
		$lookup->method( 'getDataTypeIdForProperty' )
			->willReturnCallback( static function ( PropertyId $propertyId ) use ( $lookupException ) {
				if ( $propertyId->getSerialization() === 'P404' ) {
					throw $lookupException;
				}
				return 'time';
			} );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				$this->stringContains( 'Configured property {propertyId} for {fieldName} could not be found' ),
				[
					'propertyId' => 'P404',
					'fieldName' => 'test_statement_time',
					'exception' => $lookupException,
				]
			);

		$field = new class(
			new DataTypeFactory( [] ),
			$lookup,
			[ 'P1', 'P404' ],
			[],
			[],
			[],
			$logger
		) extends StatementTimeField {
			public const NAME = 'test_statement_time';
		};

		$this->assertSame(
			[
				'type' => 'object',
				'dynamic' => false,
				'properties' => [
					'P1' => [
						'type' => 'date',
						'format' => 'date_optional_time',
					],
					'P404' => [
						'type' => 'date',
						'format' => 'date_optional_time',
					],
				],
			],
			$field->getMapping( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testGetEngineHints() {
		$field = $this->newField( $this->getPropertyTypeLookup( [] ) );
		$this->assertSame(
			[ CirrusIndexField::NOOP_HINT => 'equals' ],
			$field->getEngineHints( $this->createMock( CirrusSearch::class ) )
		);
	}

	public function testGetMappingNotCirrus() {
		$field = $this->newField( $this->getPropertyTypeLookup( [] ) );
		$this->assertSame( [], $field->getMapping( $this->createMock( SearchEngine::class ) ) );
	}
}
