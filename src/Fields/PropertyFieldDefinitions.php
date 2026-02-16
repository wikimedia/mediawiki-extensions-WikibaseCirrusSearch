<?php

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * Search fields that are used for properties.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class PropertyFieldDefinitions implements FieldDefinitions {

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

		return $fields;
	}

}
