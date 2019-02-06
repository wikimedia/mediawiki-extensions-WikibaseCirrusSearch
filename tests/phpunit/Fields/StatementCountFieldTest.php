<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\Search\Elastic\Fields\StatementCountField;
use Wikibase\Search\Elastic\Fields\WikibaseNumericField;

/**
 * @covers \Wikibase\Search\Elastic\Fields\StatementCountField
 *
 * @group WikibaseElastic
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class StatementCountFieldTest extends WikibaseNumericFieldTestCase {

	/**
	 * @return WikibaseNumericField
	 */
	protected function getFieldObject() {
		return new StatementCountField();
	}

	public function getFieldDataProvider() {
		$item = new Item();
		$item->getStatements()->addNewStatement( new PropertyNoValueSnak( 1 ) );

		return [
			[ 1, $item ],
			[ 0, Property::newFromType( 'string' ) ]
		];
	}

}
