<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\WikibaseDescriptionsIndexField;

/**
 * @covers \Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions
 *
 * @group WikibaseElastic
 * @group WikibaseRepo
 * @group Wikibase
 */
class DescriptionProviderFieldDefinitionsTest extends SearchFieldTestCase {

	public function testGetFields() {
		$languageCodes = [ 'ar', 'es' ];
		$fieldDefinitions = new DescriptionsProviderFieldDefinitions(
			$languageCodes, null
		);

		$fields = $fieldDefinitions->getFields();
		$this->assertArrayHasKey( 'descriptions', $fields );
		$this->assertInstanceOf( DescriptionsField::class, $fields['descriptions'] );
		$this->assertContainsOnlyInstancesOf( WikibaseDescriptionsIndexField::class, $fields );

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$searchEngine = $this->getSearchEngineMock();

		$mapping = $fields['descriptions']->getMapping( $searchEngine );
		$this->assertEquals( $languageCodes, array_keys( $mapping['properties'] ) );
	}

}
