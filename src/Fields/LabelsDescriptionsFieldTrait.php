<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Fields;

use CirrusSearch\CirrusSearch;
use MediaWiki\Search\SearchEngine;
use MediaWiki\Search\SearchIndexField;

/**
 * Trait for code shared between {@link LabelsField} and {@link DescriptionsField}.
 */
trait LabelsDescriptionsFieldTrait {

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @param string[] $languages List of available languages
	 * @param array $stemmingSettings Stemming config
	 */
	public function __construct(
		private readonly array $languages,
		private readonly array $stemmingSettings,
	) {
		/* @phan-suppress-next-line PhanTraitParentReference, PhanUndeclaredConstantOfClass */
		parent::__construct( static::NAME, SearchIndexField::INDEX_TYPE_NESTED );
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
