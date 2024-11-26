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
	 * @var string[]
	 */
	private $languageCodes;
	/**
	 * @var array
	 */
	private $stemmingSettings;

	/**
	 * @param string[] $languageCodes
	 * @param ConfigFactory|null $configFactory
	 */
	public function __construct( array $languageCodes, ?ConfigFactory $configFactory = null ) {
		$this->languageCodes = $languageCodes;
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
