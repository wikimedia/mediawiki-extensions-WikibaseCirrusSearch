<?php

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * Definitions for any entity that has labels.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class LabelsProviderFieldDefinitions implements FieldDefinitions {

	/**
	 * @var string[]
	 */
	private $languageCodes;

	/**
	 * @var array
	 */
	private $stemmingSettings;

	/**
	 * @param string[] $languageCodes
	 * @param array $stemmingSettings
	 */
	public function __construct( array $languageCodes, array $stemmingSettings = [] ) {
		$this->languageCodes = $languageCodes;
		$this->stemmingSettings = $stemmingSettings;
	}

	/**
	 * @return WikibaseIndexField[]
	 */
	public function getFields() {
		return [
			LabelCountField::NAME => new LabelCountField(),
			LabelsField::NAME => new LabelsField( $this->languageCodes, $this->stemmingSettings ),
			AllLabelsField::NAME => new AllLabelsField(),
		];
	}

}
