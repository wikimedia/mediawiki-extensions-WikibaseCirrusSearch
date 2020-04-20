<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use ExtensionRegistry;
use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;

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
			$languageCodes, []
		);

		$fields = $fieldDefinitions->getFields();
		$this->assertArrayHasKey( 'descriptions', $fields );
		$this->assertInstanceOf( DescriptionsField::class, $fields['descriptions'] );

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
		$searchEngine = $this->getSearchEngineMock();

		$mapping = $fields['descriptions']->getMapping( $searchEngine );
		$this->assertEquals( $languageCodes, array_keys( $mapping['properties'] ) );
	}

}
