<?php

namespace Wikibase\Search\Elastic\Tests;

use MediaWiki\Parser\ParserOutput;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityInstanceHolder;
use Wikibase\Repo\Content\PropertyContent;
use Wikibase\Repo\Content\PropertyHandler;
use Wikibase\Repo\Tests\Content\EntityHandlerTestCase;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Content\PropertyHandler
 * @covers \Wikibase\Repo\Content\EntityHandler
 *
 * @group Wikibase
 * @group WikibaseProperty
 * @group WikibaseEntity
 * @group WikibaseEntityHandler
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyHandlerTest extends EntityHandlerTestCase {

	/**
	 * @see EntityHandlerTestCase::getModelId
	 * @return string
	 */
	public function getModelId() {
		return PropertyContent::CONTENT_MODEL_ID;
	}

	/**
	 * @see EntityHandlerTestCase::contentProvider
	 */
	public static function contentProvider(): array {
		return [];
	}

	/**
	 * @return PropertyContent
	 */
	protected static function newEmptyContent() {
		return new PropertyContent();
	}

	protected static function newEntity( ?EntityId $id = null ) {
		if ( !$id ) {
			$id = new NumericPropertyId( 'P7' );
		}

		$property = Property::newFromType( 'string' );
		$property->setId( $id );
		return $property;
	}

	public static function entityIdProvider() {
		return [
			[ 'P7' ]
		];
	}

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @return PropertyHandler
	 */
	protected function getHandler( ?SettingsArray $settings = null ) {
		$this->getWikibaseRepo( $settings ); // updates services as needed
		return WikibaseRepo::getPropertyHandler();
	}

	protected static function newEntityContent( ?EntityDocument $entity = null ): EntityContent {
		if ( $entity === null ) {
			$entity = self::newEntity();
		}

		return new PropertyContent( new EntityInstanceHolder( $entity ) );
	}

	protected static function newRedirectContent( EntityId $id, EntityId $target ): ?EntityContent {
		return null;
	}

	protected static function getEntityTypeDefinitionsConfiguration(): array {
		return wfArrayPlus2d(
			require __DIR__ . '/../../WikibaseSearch.entitytypes.php',
			parent::getEntityTypeDefinitionsConfiguration()
		);
	}

	protected function getTestContent() {
		$property = new Property( null, null, 'string' );
		$property->getFingerprint()->setLabel( 'en', 'Kitten' );
		$property->getStatements()->addNewStatement(
			new PropertyNoValueSnak( new NumericPropertyId( 'P1' ) )
		);

		return PropertyContent::newFromProperty( $property );
	}

	protected function getExpectedSearchIndexFields() {
		return [ 'statement_count' ];
	}

	public function testDataForSearchIndex() {
		$handler = $this->getHandler();
		$engine = $this->createMock( \SearchEngine::class );

		$page = $this->getMockWikiPage( $handler );

		$data = $handler->getDataForSearchIndex( $page, new ParserOutput(), $engine );
		$this->assertSame( 1, $data['statement_count'], 'statement_count' );
	}

}
