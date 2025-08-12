<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\Search\BaseResultsType;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Fields\LabelsField;

/**
 * This result type implements the result for searching
 * an entity by its {@link LabelsField label or alias}
 * (also showing {@link DescriptionsField descriptions}).
 *
 * Fully implemented by {@link EntityElasticTermResult} for Wikibase entities.
 * May also be used by other extensions,
 * provided they use those same fields
 * (via {@link \Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions LabelsProviderFieldDefinitions}
 * and {@link \Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions DescriptionsProviderFieldDefinitions}).
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
abstract class ElasticTermResult extends BaseResultsType {

	/**
	 * List of language codes in the search fallback chain, the first
	 * is the preferred language.
	 * @var string[]
	 */
	private $searchLanguageCodes;

	/**
	 * Display fallback chain.
	 * @var TermLanguageFallbackChain
	 */
	private $termFallbackChain;
	private string $highlightSubField;

	/**
	 * @param string[] $searchLanguageCodes Language fallback chain for search
	 * @param TermLanguageFallbackChain $displayFallbackChain Fallback chain for display
	 * @param string $highlightSubField 'prefix' or 'plain'
	 */
	public function __construct(
		array $searchLanguageCodes,
		TermLanguageFallbackChain $displayFallbackChain,
		string $highlightSubField = 'prefix'
	) {
		$this->searchLanguageCodes = $searchLanguageCodes;
		$this->termFallbackChain = $displayFallbackChain;
		$this->highlightSubField = $highlightSubField;
	}

	/**
	 * Get the source filtering to be used loading the result.
	 *
	 * @return string[]
	 */
	public function getSourceFiltering() {
		$fields = parent::getSourceFiltering();
		foreach ( $this->termFallbackChain->getFetchLanguageCodes() as $code ) {
			$fields[] = LabelsField::NAME . '.' . $code;
			$fields[] = DescriptionsField::NAME . '.' . $code;
		}
		return $fields;
	}

	/**
	 * Get the fields to load.  Most of the time we'll use source filtering instead but
	 * some fields aren't part of the source.
	 *
	 * @return string[]
	 */
	public function getFields() {
		return [];
	}

	/**
	 * Get the highlighting configuration.
	 *
	 * @param array $highlightSource configuration for how to highlight the source.
	 *  Empty if source should be ignored.
	 * @return array|null highlighting configuration for elasticsearch
	 */
	public function getHighlightingConfiguration( array $highlightSource ) {
		$config = [
			'pre_tags' => [ '' ],
			'post_tags' => [ '' ],
			'fields' => [],
		];
		$config['fields']['title'] = [
			'type' => 'experimental',
			'fragmenter' => "none",
			'number_of_fragments' => 0,
			'matched_fields' => [ 'title.keyword' ]
		];
		$labelsName = LabelsField::NAME;
		$order = $this->highlightSubField === 'plain' ? 'score' : 'none';
		foreach ( $this->searchLanguageCodes as $code ) {
			$config['fields']["$labelsName.$code.{$this->highlightSubField}"] = [
				'type' => 'experimental',
				'fragmenter' => "none",
				'order' => $order,
				'number_of_fragments' => 0,
				'options' => [
					'skip_if_last_matched' => true,
					'return_snippets_and_offsets' => true
				],
			];
		}
		$config['fields']["$labelsName.*.{$this->highlightSubField}"] = [
			'type' => 'experimental',
			'fragmenter' => "none",
			'order' => $order,
			'number_of_fragments' => 0,
			'options' => [
				'skip_if_last_matched' => true,
				'return_snippets_and_offsets' => true
			],
		];

		return $config;
	}

	/**
	 * Convert search result from ElasticSearch result set to TermSearchResult.
	 * @param \Elastica\ResultSet $result
	 * @return TermSearchResult[] Set of search results, the types of which vary by implementation.
	 */
	public function transformElasticsearchResult( \Elastica\ResultSet $result ) {
		$results = [];
		foreach ( $result->getResults() as $r ) {
			$sourceData = $r->getSource();

			// Highlight part contains information about what has actually been matched.
			$highlight = $r->getHighlights();
			$displayLabel = EntitySearchUtils::findTermForDisplay( $sourceData, LabelsField::NAME, $this->termFallbackChain );
			$displayDescription = EntitySearchUtils::findTermForDisplay( $sourceData, DescriptionsField::NAME, $this->termFallbackChain );

			if ( !empty( $highlight['title'] ) ) {
				// If we matched title, this means it's a match by ID
				$matchedTermType = 'entityId';
				$matchedTerm = new Term( 'qid', $sourceData['title'] );
			} elseif ( !$highlight ) {
				// Something went wrong, we don't have any highlighting data
				continue;
			} else {
				[ $matchedTermType, $langCode, $term ] =
					$this->extractTermFromHighlight( $highlight, $sourceData );
				$matchedTerm = new Term( $langCode, $term );
			}

			if ( !$displayLabel ) {
				// This should not happen, but just in case, it's better to return something
				$displayLabel = $matchedTerm;
			}

			$termSearchResult = $this->getTermSearchResult(
				$sourceData, $matchedTerm, $matchedTermType, $displayLabel, $displayDescription
			);
			if ( $termSearchResult !== null ) {
				$results[$termSearchResult->getEntityIdSerialization()] = $termSearchResult;
			}
		}

		return $results;
	}

	/**
	 * Turn the given result data into a {@link TermSearchResult}
	 * (or skip this result if null is returned).
	 */
	abstract protected function getTermSearchResult(
		array $sourceData,
		Term $matchedTerm,
		string $matchedTermType,
		?Term $displayLabel,
		?Term $displayDescription
	): ?TermSearchResult;

	/**
	 * New highlighter pattern.
	 * The new highlighter can return offsets as: 1:1-XX:YY|Text Snippet
	 * or even SNIPPET_START:MATCH1_START-MATCH1_END,MATCH2_START-MATCH2_END,...:SNIPPET_END|Text
	 */
	public const HIGHLIGHT_PATTERN = '/^\d+:\d+-\d+(?:,\d+-\d+)*:\d+\|(.+)/';

	/**
	 * Extract term, language and type from highlighter results.
	 * @param array $highlight Data from highlighter
	 * @param array[] $sourceData Data from _source
	 * @return array Array of: [string $termType, string $languageCode, string $term]
	 */
	private function extractTermFromHighlight( array $highlight, array $sourceData ) {
		/**
		 * Highlighter returns:
		 * {
		 *   labels.en.prefix: [
		 *	  "metre"  // or "0:0-5:5|metre"
		 *   ]
		 * }
		 */
		$matchedTermType = 'label';
		$term = reset( $highlight ); // Take the first one
		$term = $term[0]; // Highlighter returns array
		$field = key( $highlight );
		if ( preg_match( '/^' . preg_quote( LabelsField::NAME ) . "\.([^.]+)\.{$this->highlightSubField}$/", $field, $match ) ) {
			$langCode = $match[1];
			if ( preg_match( self::HIGHLIGHT_PATTERN, $term, $termMatch ) ) {
				$isFirst = ( $term[0] === '0' );
				$term = $termMatch[1];
			} else {
				$isFirst = true;
			}
			if ( !empty( $sourceData[LabelsField::NAME][$langCode] ) ) {
				// Here we have match in one of the languages we asked for.
				// Primary label always comes first, so if it's not the first one,
				// it's an alias.
				if ( $sourceData[LabelsField::NAME][$langCode][0] !== $term ) {
					$matchedTermType = 'alias';
				}
			} else {
				// Here we have match in one of the "other" languages.
				// If it's the first one in the list, it's label, otherwise it is alias.
				$matchedTermType = $isFirst ? 'label' : 'alias';
			}
		} else {
			// This is weird since we didn't ask to match anything else,
			// but we'll return it anyway for debugging.
			$langCode = 'unknown';
		}
		return [ $matchedTermType, $langCode, $term ];
	}

	/**
	 * @return TermSearchResult[] Empty set of search results
	 */
	public function createEmptyResult() {
		return [];
	}

}
