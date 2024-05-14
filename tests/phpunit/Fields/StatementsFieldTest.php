<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use DataValues\BooleanValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\Tests\ChangeOp\StatementListProviderDummy;
use Wikibase\Repo\Tests\Rdf\RdfBuilderTestData;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Fields\StatementsField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * @covers \Wikibase\Search\Elastic\Fields\StatementsField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class StatementsFieldTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	/**
	 * List of properties we handle.
	 * @var string[]
	 */
	private $properties = [ 'P1', 'P2', 'P4', 'P7', 'P8' ];

	private $testData;

	protected function setUp(): void {
		parent::setUp();

		$this->testData = new RdfBuilderTestData(
			__DIR__ . '/../data/rdf/entities', ''
		);

		$this->setService( 'WikibaseRepo.PropertyInfoLookup', $this->testData->getPropertyInfoLookup() );
	}

	public static function statementsProvider() {
		return [
			'empty' => [
				'Q1',
				[]
			],
			'Q4' => [
				'Q4',
				[ 'P2=Q42', 'P2=Q666', 'P7=simplestring',
				  'P9=http://url.acme.test\badurl?chars=\привет< >"'
				]
			],
			'Q6' => [
				'Q6',
				[
					'P7=string',
					'P7=string[P2=Q42]',
					'P7=string[P2=Q666]',
					'P7=string[P3=Universe.svg]',
					'P7=string[P6=20]',
					'P7=string[P7=simplestring]',
					'P7=string[P9=http://url.acme.test/]',
					"P7=string[P9= http://url.acme2.test/\n]",
				]
			],
			'Q7' => [
				'Q7',
				[ 'P7=string', 'P7=string2' ]
			],
			'Q8' => [
				'Q8',
				[]
			],
		];
	}

	/**
	 * @param string[] $map
	 * @return PropertyDataTypeLookup
	 */
	private function getPropertyTypeLookup( array $map ) {
		$lookup = $this->createMock( PropertyDataTypeLookup::class );

		$lookup->method( 'getDataTypeIdForProperty' )
			->willReturnCallback( static function ( PropertyId $id ) use ( $map ) {
				if ( isset( $map[$id->getSerialization()] ) ) {
					return $map[$id->getSerialization()];
				}
				return 'string';
			} );

		return $lookup;
	}

	/**
	 * @dataProvider statementsProvider
	 */
	public function testStatements( string $entityId, array $expected ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$entity = $this->testData->getEntity( $entityId );

		$lookup = $this->getPropertyTypeLookup( [
			'P9' => 'sometype',
			'P11' => 'sometype',
		] );

		$field = new StatementsField( $lookup, $this->properties, [ 'sometype' ], [ 'P11' ],
			WikibaseRepo::getDataTypeDefinitions()->getSearchIndexDataFormatterCallbacks() );
		$this->assertEquals( $expected, $field->getFieldData( $entity ) );
	}

	public function testFormatters() {
		$formatters = [
			'VT:string' => static function ( StringValue $s ) {
				return 'STRING:' . $s->getValue();
			},
			'VT:quantity' => static function ( UnboundedQuantityValue $v ) {
				return 'VALUE:' . $v->getAmount();
			},
		];
		$lookup = $this->getPropertyTypeLookup( [
			'P9' => 'sometype',
		] );
		$field = new StatementsField( $lookup, [ 'P123' ], [], [], $formatters );

		$statementList = new StatementList();
		$statementList->addNewStatement( new PropertyValueSnak( 123, new StringValue( 'testString' ) ) );
		$statementList->addNewStatement( new PropertyValueSnak( 123, UnboundedQuantityValue::newFromNumber( 456 ) ) );
		$statementList->addNewStatement( new PropertySomeValueSnak( 123 ) );
		$statementList->addNewStatement( new PropertyValueSnak( 123, new StringValue( 'testString2' ) ) );
		$statementList->addNewStatement( new PropertyNoValueSnak( 123 ) );
		$statementList->addNewStatement( new PropertyValueSnak( 123, new BooleanValue( false ) ) );

		$entity = $this->createMock( StatementListProviderDummy::class );
		$entity->expects( $this->once() )
			->method( 'getStatements' )
			->willReturn( $statementList );

		$expected = [
			'P123=STRING:testString',
			'P123=VALUE:+456',
			'P123=STRING:testString2'
		];

		$data = $field->getFieldData( $entity );
		$this->assertEquals( $expected, $data );
	}

}
