<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\Search\SearchContext;
use Elastica\Query\AbstractQuery;
use Elastica\Query\MatchNone;
use MediaWiki\Language\Language;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\EntitySearchException;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Search\Elastic\Query\LabelsCompletionQuery;

/**
 * Entity search implementation using ElasticSearch.
 * Requires CirrusSearch extension and $wgEntitySearchUseCirrus to be on.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class EntitySearchElastic implements EntitySearchHelper {
	/**
	 * Default rescore profile
	 */
	public const DEFAULT_RESCORE_PROFILE = 'wikibase_prefix';

	/**
	 * Name of the context for profile name resolution
	 */
	public const CONTEXT_WIKIBASE_PREFIX = 'wikibase_prefix_search';

	/**
	 * Name of the context for profile name resolution
	 */
	public const CONTEXT_WIKIBASE_FULLTEXT = 'wikibase_fulltext_search';

	/**
	 * Name of the profile type used to build the elastic query
	 */
	public const WIKIBASE_PREFIX_QUERY_BUILDER = 'wikibase_prefix_querybuilder';

	/**
	 * Default query builder profile for prefix searches
	 */
	public const DEFAULT_QUERY_BUILDER_PROFILE = 'default';

	/**
	 * Default query builder profile for fulltext searches
	 *
	 */
	public const DEFAULT_FULL_TEXT_QUERY_BUILDER_PROFILE = 'wikibase';

	/**
	 * Replacement syntax for statement boosting
	 * @see \CirrusSearch\Profile\SearchProfileRepositoryTransformer
	 * and repo/config/ElasticSearchRescoreFunctions.php
	 */
	public const STMT_BOOST_PROFILE_REPL = 'functions.*[type=term_boost].params[statement_keywords=_statementBoost_].statement_keywords';

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageChainFactory;

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var string[]
	 */
	private $contentModelMap;

	/**
	 * Web request context.
	 * Used for implementing debug features such as cirrusDumpQuery.
	 * @var WebRequest
	 */
	private $request;

	/**
	 * @var Language User language for display.
	 */
	private $userLang;

	/**
	 * @var CirrusDebugOptions
	 */
	private $debugOptions;

	/**
	 * @param LanguageFallbackChainFactory $languageChainFactory
	 * @param EntityIdParser $idParser
	 * @param Language $userLang
	 * @param array $contentModelMap Maps entity type => content model name
	 * @param WebRequest|null $request Web request context
	 * @param CirrusDebugOptions|null $options
	 */
	public function __construct(
		LanguageFallbackChainFactory $languageChainFactory,
		EntityIdParser $idParser,
		Language $userLang,
		array $contentModelMap,
		?WebRequest $request = null,
		?CirrusDebugOptions $options = null
	) {
		$this->languageChainFactory = $languageChainFactory;
		$this->idParser = $idParser;
		$this->userLang = $userLang;
		$this->contentModelMap = $contentModelMap;
		$this->request = $request ?: new FauxRequest();
		$this->debugOptions = $options ?: CirrusDebugOptions::fromRequest( $this->request );
	}

	/**
	 * Produce ES query that matches the arguments.
	 *
	 * @param string $text
	 * @param string $languageCode
	 * @param string $entityType
	 * @param bool $strictLanguage
	 * @param SearchContext $context
	 *
	 * @return AbstractQuery
	 */
	protected function getElasticSearchQuery(
		$text,
		$languageCode,
		$entityType,
		$strictLanguage,
		SearchContext $context
	) {
		$context->setOriginalSearchTerm( $text );
		if ( empty( $this->contentModelMap[$entityType] ) ) {
			$context->setResultsPossible( false );
			$context->addWarning( 'wikibasecirrus-search-bad-entity-type', $entityType );
			return new MatchNone();
		}
		$profile = LabelsCompletionQuery::loadProfile(
			$context->getConfig()->getProfileService(),
			$this->languageChainFactory,
			self::WIKIBASE_PREFIX_QUERY_BUILDER,
			$context->getProfileContext(),
			$context->getProfileContextParams(),
			$languageCode
		);
		return LabelsCompletionQuery::build(
			$text,
			$profile,
			$this->contentModelMap[$entityType],
			$languageCode,
			$strictLanguage,
			EntitySearchUtils::entityIdParserNormalizer( $this->idParser )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getRankedSearchResults(
		$text,
		$languageCode,
		$entityType,
		$limit,
		$strictLanguage,
		?string $profileContext = null
	) {
		$profileContext ??= self::CONTEXT_WIKIBASE_PREFIX;
		$searcher = new WikibaseEntitySearcher( 0, $limit, 'wikibase_prefix', 'wikibase-prefix', $this->debugOptions );
		$searcher->getSearchContext()->setProfileContext(
			$profileContext,
			[ 'language' => $languageCode ] );
		$query = $this->getElasticSearchQuery( $text, $languageCode, $entityType, $strictLanguage,
				$searcher->getSearchContext() );

		$searcher->setResultsType( new EntityElasticTermResult(
			$this->idParser,
			$query instanceof LabelsCompletionQuery ? $query->getSearchLanguageCodes() : [],
			'prefix',
			$this->languageChainFactory->newFromLanguage( $this->userLang )
		) );

		$result = $searcher->performSearch( $query );

		if ( $result->isOK() ) {
			$result = $result->getValue();
		} else {
			throw new EntitySearchException( $result );
		}

		if ( $searcher->isReturnRaw() ) {
			$result = $searcher->processRawReturn( $result, $this->request );
		}

		return $result;
	}

}
