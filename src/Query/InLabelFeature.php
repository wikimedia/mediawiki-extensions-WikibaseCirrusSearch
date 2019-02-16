<?php

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Query\FilterQueryFeature;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;
use Elastica\Query\MultiMatch;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\Fields\LabelsField;
use Wikibase\Search\Elastic\Fields\AllLabelsField;

/**
 * Handles the search keyword 'inlabel:'
 *
 * Allows the user to search for pages that have wikibase labels, optionally in user specified
 * languages.
 *
 * @uses CirrusSearch
 * @see https://phabricator.wikimedia.org/T215967
 */
class InLabelFeature extends SimpleKeywordFeature implements FilterQueryFeature {
	/** @var int A limit to the number of fields that can be queried at once */
	const MAX_FIELDS = 30;

	/** @var LanguageFallbackChainFactory */
	private $languageChainFactory;

	/** @var true[] Keyed by known language codes for set membership check */
	private $languages;

	/**
	 * @param LanguageFallbackChainFactory $languageChainFactory
	 * @param string[] $languages list of languages indexed in elastic. Must all be lowercase.
	 */
	public function __construct( LanguageFallbackChainFactory $languageChainFactory, $languages ) {
		$this->languageChainFactory = $languageChainFactory;
		$this->languages = [];
		foreach ( $languages as $lang ) {
			$this->languages[$lang] = true;
		}
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'inlabel' ];
	}

	/**
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsedValue = $this->parseValue(
			$key,
			$value,
			$quotedValue,
			'',
			'',
			$context
		);
		if ( $parsedValue['fields'] === [] ) {
			$context->setResultsPossible( false );
			return [ null, false ];
		}

		// TODO: This false should be true, but it's not quite right. It will keep
		// the whole quotedValue, but we want it to only keep the search query
		// portion. Possibly we want to influence ranking with the language
		// chain as well?
		return [ $this->makeQuery( $parsedValue ), false ];
	}

	/**
	 * Builds an OR between the fields in $parsedValue. The
	 * search terms must exist wholly within a single field.
	 *
	 * @param array $parsedValue
	 * @return \Elastica\Query\BoolQuery
	 */
	private function makeQuery( array $parsedValue ) {
		$query = ( new MultiMatch() )
			->setQuery( $parsedValue['string'] )
			// AND means all terms must exist in one language label.
			// Only 1 of the provided fields must match.
			->setOperator( MultiMatch::OPERATOR_AND )
			->setFields( $parsedValue['fields'] );
		if ( $parsedValue['phrase'] ) {
			$query->setType( MultiMatch::TYPE_PHRASE );
		}
		return $query;
	}

	/**
	 * @param string $languageString
	 * @param WarningColletor $warningCollector
	 * @return string[]
	 */
	private function parseLanguages( $languageString, WarningCollector $warningCollector ): array {
		$fields = [];
		foreach ( explode( ',', $languageString ) as $languageCode ) {
			$languageCode = mb_strtolower( $languageCode );
			$withFallbacks = false;
			$withoutEnFallback = false;
			$len = strlen( $languageCode );
			if ( $len > 1 && $languageCode[$len - 1] === '*' ) {
				$languageCode = substr( $languageCode, 0, -1 );
				$withFallbacks = true;
				$len--;
			} elseif ( $len > 1 && $languageCode[$len - 1] === '+' ) {
				$languageCode = substr( $languageCode, 0, -1 );
				$withFallbacks = true;
				$withoutEnFallback = true;
				$len--;
			}

			if ( !isset( $this->languages[$languageCode] ) ) {
				$warningCollector->addWarning(
					'wikibasecirrussearch-inlabel-unknown-language-code',
					$languageCode );
				continue;
			}

			$fields[LabelsField::NAME . '.' . $languageCode] = true;
			$langCodeFields = [ LabelsField::NAME . '.' . $languageCode ];
			if ( $withFallbacks ) {
				$fallbacks = $this->languageChainFactory
					->newFromLanguageCode( $languageCode )
					->getFetchLanguageCodes();
				foreach ( $fallbacks as $fallbackCode ) {
					if ( $withoutEnFallback && $fallbackCode == 'en' ) {
						continue;
					}
					$fields[LabelsField::NAME . '.' . $fallbackCode] = true;
				}
			}
		}
		return array_keys( $fields );
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array [
	 * 		'string' => string to search for
	 * 		'fields' => array of document fields to run the query against,
	 * 		'phrase' => boolean indicating if a phrase query should be issued
	 * 	]
	 */
	public function parseValue(
		$key,
		$value,
		$quotedValue,
		$valueDelimiter,
		$suffix,
		WarningCollector $warningCollector
	) {
		$isPhrase = $quotedValue !== $value;
		if ( strlen( $value ) === 0 ) {
			$warningCollector->addWarning(
				'wikibasecirrussearch-inlabel-no-query-provided' );
			return [
				'fields' => [],
				'string' => $value,
				'phrase' => $isPhrase,
			];
		}
		$atPos = strrpos( $value, '@' );
		if ( $atPos === false ) {
			return [
				'fields' => [ AllLabelsField::NAME ],
				'string' => $value,
				'phrase' => $isPhrase,
			];
		}
		$search = substr( $value, 0, $atPos );
		if ( strlen( $search ) === 0 ) {
			$warningCollector->addWarning(
				'wikibasecirrussearch-inlabel-no-query-provided' );
			return [
				'fields' => [],
				'string' => $search,
				'phrase' => $isPhrase,
			];
		}

		$languages = substr( $value, $atPos + 1 );
		// when $atPos + 1 === strlen( $value ) hhvm
		// will return false and php7 will return ''
		if ( $languages === false || $languages === '' || $languages === '*' ) {
			return [
				'fields' => [ AllLabelsField::NAME ],
				'string' => $search,
				'phrase' => $isPhrase,
			];
		}
		$fields = $this->parseLanguages( $languages, $warningCollector );
		if ( count( $fields ) > self::MAX_FIELDS ) {
			$warningCollector->addWarning(
					'wikibasecirrussearch-inlabel-too-many-language-codes',
					self::MAX_FIELDS, count( $fields ) );
			$fields = array_slice( $fields, 0, self::MAX_FIELDS );
		}

		return [
			'fields' => $fields,
			'string' => $search,
			'phrase' => $isPhrase,
		];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		$parsedValue = $node->getParsedValue();
		if ( $parsedValue['fields'] === [] ) {
			return null;
		}
		return $this->makeQuery( $parsedValue );
	}

}
