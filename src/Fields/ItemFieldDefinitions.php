<?php

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * Search fields that are used for items.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class ItemFieldDefinitions implements FieldDefinitions {

	/**
	 * @param FieldDefinitions[] $fieldDefinitions
	 */
	public function __construct(
		private readonly array $fieldDefinitions,
	) {
	}

	/**
	 * @return WikibaseIndexField[]
	 */
	public function getFields() {
		$fields = [];

		foreach ( $this->fieldDefinitions as $definitions ) {
			$fields = array_merge( $fields, $definitions->getFields() );
		}

		$fields[SiteLinkCountField::NAME] = new SiteLinkCountField();

		return $fields;
	}

}
