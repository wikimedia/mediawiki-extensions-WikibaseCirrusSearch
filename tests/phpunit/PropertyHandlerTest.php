<?php

namespace Wikibase\Search\Elastic\Tests;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\PropertyContent;
use Wikibase\Repo\Content\PropertyHandler;
use Wikibase\Repo\Tests\Content\EntityHandlerTestCase;
use Wikibase\SettingsArray;

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
		return CONTENT_MODEL_WIKIBASE_PROPERTY;
	}

	/**
	 * @see EntityHandlerTestCase::contentProvider
	 */
	public function contentProvider() {
		return [];
	}

	protected function newEntity( EntityId $id = null ) {
		if ( !$id ) {
			$id = new PropertyId( 'P7' );
		}

		$property = Property::newFromType( 'string' );
		$property->setId( $id );
		return $property;
	}

	public function entityIdProvider() {
		return [
			[ 'P7' ]
		];
	}

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @return PropertyHandler
	 */
	protected function getHandler( SettingsArray $settings = null ) {
		return $this->getWikibaseRepo( $settings )->newPropertyHandler();
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

	protected function getTestContent() {
		$property = new Property( null, null, 'string' );
		$property->getFingerprint()->setLabel( 'en', 'Kitten' );
		$property->getStatements()->addNewStatement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) )
		);

		return PropertyContent::newFromProperty( $property );
	}

	protected function getExpectedSearchIndexFields() {
		return [ 'label_count', 'statement_count' ];
	}

	public function testDataForSearchIndex() {
		$handler = $this->getHandler();
		$engine = $this->getMock( \SearchEngine::class );

		$page = $this->getMockWikiPage( $handler );

		$data = $handler->getDataForSearchIndex( $page, new \ParserOutput(), $engine );
		$this->assertSame( 1, $data['label_count'], 'label_count' );
		$this->assertSame( 1, $data['statement_count'], 'statement_count' );
	}

}
