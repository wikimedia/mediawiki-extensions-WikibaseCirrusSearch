<?php

namespace Wikibase\Search\Elastic\Fields;

use MediaWiki\Config\ConfigFactory;
use Wikibase\Repo\Search\Fields\FieldDefinitions;

/**
 * Definitions for any entity that has descriptions.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class DescriptionsProviderFieldDefinitions implements FieldDefinitions {

	/**
	 * @var array
	 */
	private $stemmingSettings;

	/**
	 * @param string[] $languageCodes
	 * @param ConfigFactory|null $configFactory
	 */
	public function __construct(
		private readonly array $languageCodes,
		?ConfigFactory $configFactory = null,
	) {
		if ( $configFactory === null ) {
			$this->stemmingSettings = [];
		} else {
			$this->stemmingSettings = $configFactory->makeConfig( 'WikibaseCirrusSearch' )
				->get( 'UseStemming' );
		}
	}

	/**
	 * @return WikibaseDescriptionsIndexField[]
	 */
	public function getFields() {
		return [
			DescriptionsField::NAME => new DescriptionsField( $this->languageCodes, $this->stemmingSettings ),
		];
	}

}
