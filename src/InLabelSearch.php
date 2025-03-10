<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\Search\SearchContext;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\EntitySearchException;
use Wikibase\Search\Elastic\Query\InLabelQuery;

/**
 * @license GPL-2.0-or-later
 */
class InLabelSearch {

	private LanguageFallbackChainFactory $languageChainFactory;

	private EntityIdParser $idParser;
	private array $contentModelMap;
	private CirrusDebugOptions $debugOptions;

	public function __construct(
		LanguageFallbackChainFactory $languageChainFactory,
		EntityIdParser $idParser,
		array $contentModelMap,
		CirrusDebugOptions $debugOptions
	) {
		$this->languageChainFactory = $languageChainFactory;
		$this->idParser = $idParser;
		$this->contentModelMap = $contentModelMap;
		$this->debugOptions = $debugOptions;
	}

	/**
	 * @return TermSearchResult[]
	 *
	 * @throws EntitySearchException
	 */
	public function search(
		string $searchTerm,
		string $languageCode,
		string $entityType,
		int $limit
	): array {
		$searcher = new WikibaseEntitySearcher(
			0,
			$limit,
			'wikibase_in_label',
			'wikibase-in-label',
			$this->debugOptions
		);
		$searcher->getSearchContext()->setProfileContext(
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL,
			[ 'language' => $languageCode ] );
		$query = $this->getElasticSearchQuery( $searchTerm, $languageCode, $entityType, $searcher->getSearchContext() );

		$searcher->setResultsType( new EntityElasticTermResult(
			$this->idParser,
			$query->getSearchLanguageCodes(),
			'plain',
			$this->languageChainFactory->newFromLanguageCode( $languageCode )
		) );

		$result = $searcher->performSearch( $query );

		if ( $result->isOK() ) {
			$result = $result->getValue();
		} else {
			throw new EntitySearchException( $result );
		}

		return $result;
	}

	private function getElasticSearchQuery(
		string $text,
		string $languageCode,
		string $entityType,
		SearchContext $context
	): InLabelQuery {
		$context->setOriginalSearchTerm( $text );
		$profile = InLabelQuery::loadProfile(
			$context->getConfig()->getProfileService(),
			$this->languageChainFactory,
			EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			$context->getProfileContext(),
			$context->getProfileContextParams(),
			$languageCode
		);
		return InLabelQuery::build(
			$text,
			$profile,
			$this->contentModelMap[$entityType],
			$languageCode,
			false,
			EntitySearchUtils::entityIdParserNormalizer( $this->idParser )
		);
	}

}
