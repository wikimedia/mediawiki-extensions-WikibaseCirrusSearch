<?php

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Profile\SearchProfileService;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\DisMax;
use Elastica\Query\MatchQuery;
use Elastica\Query\Term;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\EntitySearchUtils;
use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Fields\LabelsField;

/**
 * Query used to perform search on the multilanguage labels field.
 */
class InLabelQuery extends AbstractQuery {
	private string $normalizedQuery;
	private array $profile;
	private string $languageCode;
	private array $searchLanguageCodes;
	private bool $strictLanguage;
	private string $contentModel;
	/**
	 * @var string|null an id identified in the user query that might match the title field
	 */
	private ?string $normalizedId;

	private function __construct(
		string $normalizedQuery,
		array $profile,
		string $languageCode,
		array $searchLanguageCodes,
		bool $strictLanguage,
		string $contentModel,
		?string $normalizedId
	) {
		$this->normalizedQuery = $normalizedQuery;
		$this->profile = $profile;
		$this->languageCode = $languageCode;
		$this->searchLanguageCodes = $searchLanguageCodes;
		$this->strictLanguage = $strictLanguage;
		$this->contentModel = $contentModel;
		$this->normalizedId = $normalizedId;
	}

	/**
	 * Build the InLabelQuery based on the user query.
	 * This function does minimal parsing of the input query:
	 * - trim spaces
	 * - attempt to find possible references of an object ID if $idNormalizer is provided
	 *
	 * @param string $userQuery the user query
	 * @param array $profile the profile to build the query
	 * @param string $contentModel the content model
	 * @param string $languageCode the language code (generally the user language)
	 * @param bool $strictLanguage whether we're interested in matching language fallbacks or not
	 * (use false to match fallbacks)
	 * @param callable(string):(string|null)|null $idNormalizer optional function to normalize parts of the user query
	 * that resembles an ID of the type of object we're searching. The function must return null
	 * if its argument is not something that can be parsed as an ID.
	 *
	 * @return self
	 */
	public static function build(
		string $userQuery,
		array $profile,
		string $contentModel,
		string $languageCode,
		bool $strictLanguage,
		?callable $idNormalizer = null
	): self {
		$normalizedQuery = trim( $userQuery );
		$normalizedId = $idNormalizer !== null ? EntitySearchUtils::normalizeIdFromSearchQuery( $normalizedQuery, $idNormalizer ) : null;
		$searchLanguageCodes = $profile['language-chain'];
		if ( $languageCode !== $searchLanguageCodes[0] ) {
			// Log a warning? Are there valid reasons for the primary language
			// in the profile to not match the profile request?
			$languageCode = $searchLanguageCodes[0];
		}
		return new self(
			$normalizedQuery,
			$profile,
			$languageCode,
			$searchLanguageCodes,
			$strictLanguage,
			$contentModel,
			$normalizedId
		);
	}

	/**
	 * Helper function to load and "expand" the search profile for this query.
	 * The returned profile is what is meant to be passed to {@link self::build()}.
	 *
	 * @param SearchProfileService $searchProfileService
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param string $queryBuilderType the type of query builder for which profiles are set
	 * (e.g. {@link \Wikibase\Search\Elastic\Hooks::registerSearchProfiles()} and
	 * {@link \Wikibase\Search\Elastic\EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER})
	 * @param string $profileContextName the name of the search context used to determined what is
	 * the default profile to use in this context
	 * (e.g. {@link \Wikibase\Search\Elastic\Hooks::registerSearchProfiles()} and
	 * {@link \Wikibase\Search\Elastic\EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX})
	 * @param array $contextParams the context parameters (i.e. used to hold the user language)
	 * @param string $languageCode the user language code
	 * @return array
	 */
	public static function loadProfile(
		SearchProfileService $searchProfileService,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		string $queryBuilderType,
		string $profileContextName,
		array $contextParams,
		string $languageCode
	): array {
		$profile = $searchProfileService->loadProfile( $queryBuilderType, $profileContextName, null, $contextParams );

		// Set some bc defaults for properties that didn't always exist.
		$profile['tie-breaker'] ??= 0;

		// There are two flavors of profiles: fully specified, and generic
		// fallback. When language-chain is provided we assume a fully
		// specified profile. Otherwise we expand the language agnostic
		// profile into a language specific profile.
		if ( !isset( $profile['language-chain'] ) ) {
			$profile = self::expandGenericProfile( $languageCode, $profile, $languageFallbackChainFactory );
		}

		return $profile;
	}

	private static function expandGenericProfile(
		string $languageCode,
		array $profile,
		LanguageFallbackChainFactory $languageFallbackChainFactory
	): array {
		$res = [
			'language-chain' => $languageFallbackChainFactory
				->newFromLanguageCode( $languageCode )
				->getFetchLanguageCodes(),
			'any' => $profile['any'],
			'tie-breaker' => $profile['tie-breaker'],
			'space-discount' => $profile['space-discount'] ?? null,
			"{$languageCode}-exact" => $profile['lang-exact'],
			"{$languageCode}-folded" => $profile['lang-folded'],
			"{$languageCode}-tokenized" => $profile['lang-tokenized'],
		];

		$discount = $profile['fallback-discount'];
		foreach ( $res['language-chain'] as $fallback ) {
			if ( $fallback === $languageCode ) {
				continue;
			}
			$res["{$fallback}-exact"] = $profile['fallback-exact'] * $discount;
			$res["{$fallback}-folded"] = $profile['fallback-folded'] * $discount;
			$res["{$fallback}-tokenized"] = $profile['fallback-tokenized'] * $discount;
			$discount *= $profile['fallback-discount'];
		}

		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function toArray() {
		$query = new BoolQuery();

		$labelsName = LabelsField::NAME;
		$allLabelsName = AllLabelsField::NAME;

		$labelsFilter = new MatchQuery( "$allLabelsName.plain", [ 'query' => $this->normalizedQuery ] );
		$labelsFilter->setFieldOperator( "$allLabelsName.plain", MatchQuery::OPERATOR_AND );

		$languageCode = $this->languageCode;
		$profile = $this->profile;
		$fields = [
			[ "$labelsName.{$languageCode}.near_match", $profile["{$languageCode}-exact"], MatchQuery::OPERATOR_OR ],
			[ "$labelsName.{$languageCode}.near_match_folded", $profile["{$languageCode}-folded"], MatchQuery::OPERATOR_OR ],
		];
		$weight = $profile["{$languageCode}-tokenized"];

		$fields[] = [ "labels.{$languageCode}.plain", $weight, MatchQuery::OPERATOR_AND ];

		if ( !$this->strictLanguage ) {
			$fields[] = [ "labels_all.near_match_folded", $profile['any'], MatchQuery::OPERATOR_OR ];
			foreach ( $this->searchLanguageCodes as $fallbackCode ) {
				if ( $fallbackCode === $languageCode ) {
					continue;
				}
				$fields[] = [
					"labels.{$fallbackCode}.near_match",
					$profile["{$fallbackCode}-exact"],
					MatchQuery::OPERATOR_OR ];
				$fields[] = [
					"labels.{$fallbackCode}.near_match_folded",
					$profile["{$fallbackCode}-folded"],
					MatchQuery::OPERATOR_OR ];

				$weight = $profile["{$fallbackCode}-tokenized"];
				$fields[] = [ "labels.{$fallbackCode}.plain", $weight, MatchQuery::OPERATOR_AND ];
			}
		}

		$dismax = new DisMax();
		$dismax->setTieBreaker( $profile['tie-breaker'] );
		foreach ( $fields as [ $field, $boost, $matchOperator ] ) {
			$dismax->addQuery( EntitySearchUtils::makeConstScoreQuery(
				$field,
				$boost,
				$this->normalizedQuery,
				$matchOperator
			) );
		}

		$labelsQuery = new BoolQuery();
		$labelsQuery->addFilter( $labelsFilter );
		$labelsQuery->addShould( $dismax );
		// Match either labels or exact match to title
		$query->addShould( $labelsQuery );
		if ( $this->normalizedId !== null ) {
			$titleMatch = new Term( [ 'title.keyword' => $this->normalizedId ] );
			$query->addShould( $titleMatch );
		}

		$query->setMinimumShouldMatch( 1 );

		// Filter to fetch only given entity type
		$query->addFilter( new Term( [ 'content_model' => $this->contentModel ] ) );

		return $query->toArray();
	}

	/**
	 * The language codes used by the query, generally the user language and its fallbacks.
	 * This is useful to know which language fields could be useful to highlight.
	 *
	 * @return string[] the language codes used by the query.
	 */
	public function getSearchLanguageCodes(): array {
		return $this->searchLanguageCodes;
	}
}
