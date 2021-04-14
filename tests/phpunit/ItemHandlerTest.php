<?php

namespace Wikibase\Search\Elastic\Tests;

use Title;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityInstanceHolder;
use Wikibase\Repo\Content\ItemContent;
use Wikibase\Repo\Content\ItemHandler;
use Wikibase\Repo\Tests\Content\EntityHandlerTestCase;
use Wikibase\Repo\WikibaseRepo;

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
		return ItemContent::CONTENT_MODEL_ID;
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
	protected function newEntityContent( EntityDocument $entity = null ): EntityContent {
		if ( !$entity ) {
			$entity = new Item( new ItemId( 'Q42' ) );
		}

		return new ItemContent( new EntityInstanceHolder( $entity ) );
	}

	protected function newRedirectContent( EntityId $id, EntityId $target ): EntityContent {
		$redirect = new EntityRedirect( $id, $target );

		$title = Title::makeTitle( 100, $target->getSerialization() );
		// set content model to avoid db call to look up content model when
		// constructing ItemContent in the tests, especially in the data providers.
		$title->setContentModel( ItemContent::CONTENT_MODEL_ID );

		return new ItemContent( null, $redirect, $title );
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

	protected function getEntityTypeDefinitionsConfiguration(): array {
		return wfArrayPlus2d(
			require __DIR__ . '/../../WikibaseSearch.entitytypes.php',
			parent::getEntityTypeDefinitionsConfiguration()
		);
	}

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @return ItemHandler
	 */
	protected function getHandler( SettingsArray $settings = null ) {
		$this->getWikibaseRepo( $settings ); // updates services as needed
		return WikibaseRepo::getItemHandler();
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
