<?php

namespace Wikibase\Search\Elastic\Fields;

use InvalidArgumentException;
use MediaWiki\Config\ConfigFactory;
use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

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
	public function __construct( array $languageCodes, $configFactory = null ) {
		$this->languageCodes = $languageCodes;
		if ( $configFactory === null ) {
			$this->stemmingSettings = [];
		} elseif ( $configFactory instanceof ConfigFactory ) {
			$this->stemmingSettings = $configFactory->makeConfig( 'WikibaseCirrusSearch' )
				->get( 'UseStemming' );
		} elseif ( is_array( $configFactory ) ) {
			$this->stemmingSettings = $configFactory; // B/C
		} else {
			throw new InvalidArgumentException( 'invalid $configFactory / $stemmingSettings' );
		}
	}

	/**
	 * @return WikibaseIndexField[]
	 */
	public function getFields() {
		return [
			DescriptionsField::NAME => new DescriptionsField( $this->languageCodes, $this->stemmingSettings ),
		];
	}

}
