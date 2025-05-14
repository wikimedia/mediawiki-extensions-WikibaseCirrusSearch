<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Fields;

use CirrusSearch\CirrusSearch;
use SearchEngine;
use SearchIndexField;

/**
 * Trait for code shared between {@link LabelsField} and {@link DescriptionsField}.
 */
trait LabelsDescriptionsFieldTrait {

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * List of available languages
	 * @var string[]
	 */
	private $languages;

	/**
	 * @var array
	 */
	private $stemmingSettings;

	/**
	 * @param string[] $languages Available languages list.
	 * @param array $stemmingSettings Stemming config
	 */
	public function __construct( array $languages, array $stemmingSettings ) {
		$this->languages = $languages;
		/* @phan-suppress-next-line PhanTraitParentReference, PhanUndeclaredConstantOfClass */
		parent::__construct( static::NAME, SearchIndexField::INDEX_TYPE_NESTED );
		$this->stemmingSettings = $stemmingSettings;
	}

	/**
	 * Set engine hints.
	 * Specifically, sets noop hint so that labels/descriptions would be compared
	 * as arrays and removal of labels/descriptions would be processed correctly.
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getEngineHints( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			// For now only Cirrus/Elastic is supported
			return [];
		}
		return [ \CirrusSearch\Search\CirrusIndexField::NOOP_HINT => "equals" ];
	}

	/** @inheritDoc */
	public function merge( SearchIndexField $that ) {
		if ( !( $that instanceof self ) || $this->type !== $that->type ) {
			return false;
		}

		if (
			$this->stemmingSettings == $that->stemmingSettings ||
			$that->stemmingSettings === []
		) {
			$mergedStemmingSettings = $this->stemmingSettings;
		} elseif ( $this->stemmingSettings === [] ) {
			$mergedStemmingSettings = $that->stemmingSettings;
		} else {
			return false;
		}

		$mergedLanguages = array_values( array_unique( array_merge(
			$this->languages,
			$that->languages
		) ) );

		if (
			$this->languages === $mergedLanguages &&
			$this->stemmingSettings === $mergedStemmingSettings
		) {
			return $this;
		} elseif (
			$that->languages === $mergedLanguages &&
			$that->stemmingSettings === $mergedStemmingSettings
		) {
			return $that;
		} else {
			/* @phan-suppress-next-line PhanTypeInstantiateTraitStaticOrSelf */
			return new self( $mergedLanguages, $mergedStemmingSettings );
		}
	}

}
