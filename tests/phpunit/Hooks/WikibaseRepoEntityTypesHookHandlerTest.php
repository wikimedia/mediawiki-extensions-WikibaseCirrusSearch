<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Tests\Hooks;

use MediaWikiIntegrationTestCase;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Repo\Content\ItemContent;
use Wikibase\Repo\Content\PropertyContent;
use Wikibase\Search\Elastic\Hooks\WikibaseRepoEntityTypesHookHandler;

/**
 * @covers \Wikibase\Search\Elastic\Hooks\WikibaseRepoEntityTypesHookHandler
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class WikibaseRepoEntityTypesHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testDoesNothingIfDisabled(): void {
		$this->overrideConfigValue( 'WBCSUseCirrus', false );
		$entityTypeDefinitions = [ 'item' => [], 'property' => [] ];
		$original = $entityTypeDefinitions; // copy
		$handler = new WikibaseRepoEntityTypesHookHandler();

		$handler->onWikibaseRepoEntityTypes( $entityTypeDefinitions );

		$this->assertSame( $original, $entityTypeDefinitions );
	}

	public function testOverridesCallbacks(): void {
		$this->overrideConfigValue( 'WBCSUseCirrus', true );
		$entityTypeDefinitions = [
			'item' => [
				EntityTypeDefinitions::CONTENT_MODEL_ID => ItemContent::CONTENT_MODEL_ID,
				EntityTypeDefinitions::ENTITY_SEARCH_CALLBACK => 'original item callback',
			],
			'property' => [
				EntityTypeDefinitions::CONTENT_MODEL_ID => PropertyContent::CONTENT_MODEL_ID,
				EntityTypeDefinitions::ENTITY_SEARCH_CALLBACK => 'original property callback',
			],
		];
		$handler = new WikibaseRepoEntityTypesHookHandler();

		$handler->onWikibaseRepoEntityTypes( $entityTypeDefinitions );

		$this->assertSame( ItemContent::CONTENT_MODEL_ID,
			$entityTypeDefinitions['item'][EntityTypeDefinitions::CONTENT_MODEL_ID] );
		$this->assertSame( PropertyContent::CONTENT_MODEL_ID,
			$entityTypeDefinitions['property'][EntityTypeDefinitions::CONTENT_MODEL_ID] );
		$itemCallback = $entityTypeDefinitions['item'][EntityTypeDefinitions::ENTITY_SEARCH_CALLBACK];
		$this->assertNotSame( 'original item callback', $itemCallback );
		$this->assertIsCallable( $itemCallback );
		$propertyCallback = $entityTypeDefinitions['property'][EntityTypeDefinitions::ENTITY_SEARCH_CALLBACK];
		$this->assertNotSame( 'original property callback', $propertyCallback );
		$this->assertIsCallable( $propertyCallback );
	}

	public function testKeepsEntityTypeOrder(): void {
		$this->overrideConfigValue( 'WBCSUseCirrus', true );
		$entityTypeDefinitions = [
			'property' => [],
			'item' => [],
		];
		$handler = new WikibaseRepoEntityTypesHookHandler();

		$handler->onWikibaseRepoEntityTypes( $entityTypeDefinitions );

		$this->assertSame( [ 'property', 'item' ], array_keys( $entityTypeDefinitions ) );
	}

}
