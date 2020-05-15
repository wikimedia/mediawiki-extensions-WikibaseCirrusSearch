<?php

namespace Wikibase\Search\Elastic\Tests;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityInstanceHolder;
use Wikibase\Repo\Content\ItemContent;
use Wikibase\Repo\Content\ItemHandler;
use Wikibase\Repo\Tests\Content\EntityHandlerTestCase;

/**
 * @covers \Wikibase\Repo\Content\ItemHandler
 * @covers \Wikibase\Repo\Content\EntityHandler
 *
 * @group Wikibase
 * @group WikibaseItem
 * @group WikibaseEntity
 * @group WikibaseEntityHandler
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class ItemHandlerTest extends EntityHandlerTestCase {
	use WikibaseSearchTestCase;

	/**
	 * @see EntityHandlerTestCase::getModelId
	 * @return string
	 */
	public function getModelId() {
		return CONTENT_MODEL_WIKIBASE_ITEM;
	}

	/**
	 * @see EntityHandlerTestCase::contentProvider
	 */
	public function contentProvider() {
		return [];
	}

	/**
	 * @param EntityDocument|null $entity
	 *
	 * @return EntityContent
	 */
	protected function newEntityContent( EntityDocument $entity = null ) {
		if ( !$entity ) {
			$entity = new Item( new ItemId( 'Q42' ) );
		}

		return $this->getHandler()->makeEntityContent( new EntityInstanceHolder( $entity ) );
	}

	public function entityIdProvider() {
		return [
			[ 'Q7' ],
		];
	}

	protected function newEntity( EntityId $id = null ) {
		if ( !$id ) {
			$id = new ItemId( 'Q7' );
		}

		return new Item( $id );
	}

	public function testSupportsRedirects() {
		$this->assertTrue( $this->getHandler()->supportsRedirects() );
	}

	protected function getEntityTypeDefinitions() {
		return new EntityTypeDefinitions(
			wfArrayPlus2d(
				require __DIR__ . '/../../WikibaseSearch.entitytypes.php',
				array_merge_recursive(
					require __DIR__ . '/../../../Wikibase/lib/WikibaseLib.entitytypes.php',
					require __DIR__ . '/../../../Wikibase/repo/WikibaseRepo.entitytypes.php'
				)
			)
		);
	}

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @return ItemHandler
	 */
	protected function getHandler( SettingsArray $settings = null ) {
		return $this->getWikibaseRepo( $settings )->newItemHandler();
	}

	protected function getTestContent() {
		$item = new Item();
		$item->getFingerprint()->setLabel( 'en', 'Kitten' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Kitten' );
		$item->getStatements()->addNewStatement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) )
		);

		return ItemContent::newFromItem( $item );
	}

	protected function getExpectedSearchIndexFields() {
		return [ 'label_count', 'statement_count', 'sitelink_count' ];
	}

	public function testDataForSearchIndex() {
		$handler = $this->getHandler();
		$engine = $this->createMock( \SearchEngine::class );

		$page = $this->getMockWikiPage( $handler );

		$data = $handler->getDataForSearchIndex( $page, new \ParserOutput(), $engine );
		$this->assertSame( 1, $data['label_count'], 'label_count' );
		$this->assertSame( 1, $data['sitelink_count'], 'sitelink_count' );
		$this->assertSame( 1, $data['statement_count'], 'statement_count' );
	}

}
