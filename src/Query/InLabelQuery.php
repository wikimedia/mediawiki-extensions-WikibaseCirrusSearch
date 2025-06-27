<?php declare( strict_types=1 );

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Parser\EmptyQueryClassifiersRepository;
use CirrusSearch\Parser\KeywordRegistry;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Parser\QueryParser;
use CirrusSearch\Parser\QueryStringRegex\QueryStringRegexParser;
use CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\Escaper;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\EntitySearchUtils;

/**
 * Query used to perform search on the multilanguage labels field.
 */
class InLabelQuery extends AbstractQuery {
	private string $normalizedQuery;
	private array $profile;
	private string $languageCode;
	private array $searchLanguageCodes;
	private string $contentModel;
	private QueryParser $queryParser;
	/**
	 * @var string|null an id identified in the user query that might match the title field
	 */
	private ?string $normalizedId;
	private array $stemmingSettings;

	private function __construct(
		string $normalizedQuery,
		array $profile,
		string $languageCode,
		array $searchLanguageCodes,
		string $contentModel,
		QueryParser $queryParser,
		?string $normalizedId,
		array $stemmingSettings
	) {
		$this->normalizedQuery = $normalizedQuery;
		$this->profile = $profile;
		$this->languageCode = $languageCode;
		$this->searchLanguageCodes = $searchLanguageCodes;
		$this->contentModel = $contentModel;
		$this->queryParser = $queryParser;
		$this->normalizedId = $normalizedId;
		$this->stemmingSettings = $stemmingSettings;
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
	 * @param callable(string):(string|null)|null $idNormalizer optional function to normalize parts of the user query
	 * @param array $stemmingSettings
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
		?callable $idNormalizer,
		array $stemmingSettings
	): self {
		$normalizedQuery = trim( $userQuery );
		$normalizedId = $idNormalizer !== null ? EntitySearchUtils::normalizeIdFromSearchQuery( $normalizedQuery, $idNormalizer ) : null;
		$searchLanguageCodes = $profile['language-chain'];
		if ( $languageCode !== $searchLanguageCodes[0] ) {
			// Log a warning? Are there valid reasons for the primary language
			// in the profile to not match the profile request?
			$languageCode = $searchLanguageCodes[0];
		}

		// keywords aren't supported, so create a KeywordRegistry that always returns an empty array
		$keywordRegistry = new class() implements KeywordRegistry {
			/** @inheritDoc */
			public function getKeywords(): array {
				return [];
			}
		};
		// namespace prefix is not supported, so create a NamespacePrefixParser that always returns false
		$namespacePrefixParser = new class() implements NamespacePrefixParser {
			/** @inheritDoc */
			public function parse( $query ): bool {
				return false;
			}
		};
		$queryParser = new QueryStringRegexParser(
			$keywordRegistry,
			new Escaper( $languageCode, false ),
			"none",
			new EmptyQueryClassifiersRepository(),
			$namespacePrefixParser,
			null
		);

		return new self(
			$normalizedQuery,
			$profile,
			$languageCode,
			$searchLanguageCodes,
			$contentModel,
			$queryParser,
			$normalizedId,
			$stemmingSettings
		);
	}

	/**
	 * Helper function to load and "expand" the search profile for this query.
	 * The returned profile is what is meant to be passed to {@link self::build()}.
	 *
	 * @param SearchProfileService $searchProfileService
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param string $queryBuilderType the type of query builder for which profiles are set
	 * (e.g. {@link \Wikibase\Search\Elastic\CirrusSearchHooksHandler::registerSearchProfiles()} and
	 * {@link \Wikibase\Search\Elastic\EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER})
	 * @param string $profileContextName the name of the search context used to determined what is
	 * the default profile to use in this context
	 * (e.g. {@link \Wikibase\Search\Elastic\CirrusSearchHooksHandler::registerSearchProfiles()} and
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
			"{$languageCode}-stemmed" => $profile['lang-stemmed'],
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
	 * @throws SearchQueryParseException
	 */
	public function toArray(): array {
		$parsedQuery = $this->queryParser->parse( $this->normalizedQuery );

		$baseQuery = new BoolQuery();
		$baseQuery->setMinimumShouldMatch( 1 );
		// fetch only the requested entity type
		$baseQuery->addFilter( new Term( [ 'content_model' => $this->contentModel ] ) );

		$filterVisitor = new InLabelFilterVisitor( $this->languageCode, $this->stemmingSettings );
		$parsedQuery->getRoot()->accept( $filterVisitor );
		$filterQuery = $filterVisitor->getFilterQuery();

		$scoringVisitor = new InLabelScoringVisitor( $this->stemmingSettings );
		$parsedQuery->getRoot()->accept( $scoringVisitor );
		$scoringQuery = $scoringVisitor->buildScoringQuery( $this->searchLanguageCodes, $this->profile );

		$labelsQuery = new BoolQuery();
		$labelsQuery->addFilter( $filterQuery );
		$labelsQuery->addShould( $scoringQuery );

		// Match either labels or exact match to title
		$baseQuery->addShould( $labelsQuery );
		if ( $this->normalizedId !== null ) {
			$titleMatch = new Term( [ 'title.keyword' => $this->normalizedId ] );
			$baseQuery->addShould( $titleMatch );
		}

		return $baseQuery->toArray();
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
