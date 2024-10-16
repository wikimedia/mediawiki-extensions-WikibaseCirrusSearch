<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Fields\LabelsField;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\WikibaseLabelsIndexField;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * @covers \Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions
 *
 * @group WikibaseElastic
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class LabelsProviderFieldDefinitionsTest extends SearchFieldTestCase {
	use WikibaseSearchTestCase;

	public function testGetFields() {
		$languageCodes = [ 'ar', 'es' ];
		$fieldDefinitions = new LabelsProviderFieldDefinitions(
			$languageCodes, null
		);

		$fields = $fieldDefinitions->getFields();
		$this->assertArrayHasKey( 'labels', $fields );
		$this->assertInstanceOf( LabelsField::class, $fields['labels'] );
		$this->assertArrayHasKey( 'labels_all', $fields );
		$this->assertInstanceOf( AllLabelsField::class, $fields['labels_all'] );
		foreach ( $fields as $field ) {
			$this->assertInstanceOf( WikibaseLabelsIndexField::class, $field );
		}

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$searchEngine = $this->getSearchEngineMock();

		$mapping = $fields['labels']->getMapping( $searchEngine );
		$this->assertEquals( $languageCodes, array_keys( $mapping['properties'] ) );
	}

}
