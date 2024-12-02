<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch\CirrusSearch;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\Tests\Rdf\RdfBuilderTestData;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Fields\StatementQuantityField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * @covers \Wikibase\Search\Elastic\Fields\StatementQuantityField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class StatementQuantityFieldTest extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	/**
	 * List of properties we handle.
	 * @var string[]
	 */
	private $properties = [ 'P1', 'P2', 'P4', 'P7', 'P8' ];
	/** @var string[] */
	private $propertiesForQuantity = [ 'P6' ];

	public static function statementsProvider() {
		return [
			'not a StatementListProvider' => [
				null, // mock EntityDocument
				[]
			],
			'entity with no statements' => [
				'Q1',
				[]
			],
			'entity with statements but no qualifiers' => [
				'Q4',
				[]
			],
			'entity with statements, one with a quantity qualifier' => [
				'Q6',
				[
					'P7=string|20',
				]
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();

		$lookup = $this->createMock( PropertyDataTypeLookup::class );
		$lookup->method( 'getDataTypeIdForProperty' )
			->willReturnCallback( static function ( PropertyId $id ) {
				$map = [
					'P2' => 'wikibase-item',
					'P3' => 'commonsMedia',
					'P4' => 'globe-coordinate',
					'P5' => 'monolingualtext',
					'P6' => 'quantity',
					'P7' => 'string',
					'P8' => 'time',
					'P9' => 'url',
					'P10' => 'geo-shape',
					'P11' => 'external-id',
				];
				if ( isset( $map[$id->getSerialization()] ) ) {
					return $map[$id->getSerialization()];
				}
				return 'unknown';
			} );
		$this->setService( 'WikibaseRepo.PropertyDataTypeLookup', $lookup );
	}

	private function createStatementQuantityField() {
		$services = $this->getServiceContainer();
		return new StatementQuantityField(
			WikibaseRepo::getPropertyDataTypeLookup( $services ),
			$this->properties,
			[],
			[],
			WikibaseRepo::getDataTypeDefinitions( $services )
				->getSearchIndexDataFormatterCallbacks(),
			$this->propertiesForQuantity
		);
	}

	/**
	 * @dataProvider statementsProvider
	 */
	public function testGetFieldData( ?string $entityId, array $expected ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		if ( $entityId === null ) {
			$entity = $this->createMock( EntityDocument::class );
		} else {
			$testData = new RdfBuilderTestData(
				__DIR__ . '/../data/rdf/entities', ''
			);
			$entity = $testData->getEntity( $entityId );
		}

		$field = $this->createStatementQuantityField();
		$this->assertEquals( $expected, $field->getFieldData( $entity ) );
	}

	public function testGetMapping() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$field = $this->createStatementQuantityField();
		$searchEngine = $this->createMock( CirrusSearch::class );
		$this->assertIsArray( $field->getMapping( $searchEngine ) );
	}

	public function testGetMappingNotCirrus() {
		$field = $this->createStatementQuantityField();
		$searchEngine = $this->createMock( \SearchEngine::class );
		$this->assertSame( [], $field->getMapping( $searchEngine ) );
	}

}
