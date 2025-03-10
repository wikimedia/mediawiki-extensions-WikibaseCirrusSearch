<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\Connection;
use CirrusSearch\SearchConfig;
use CirrusSearch\Searcher;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;

/**
 * Searcher class for performing Wikibase entity search.
 * @see \CirrusSearch\Searcher
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class WikibaseEntitySearcher extends Searcher {
	/**
	 * @var AbstractQuery
	 */
	private $query;
	private string $syntaxUsed;
	private string $statsKey;

	public function __construct(
		int $offset,
		int $limit,
		string $syntaxUsed,
		string $statsKey,
		?CirrusDebugOptions $options = null
	) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'CirrusSearch' );
		/** @var SearchConfig $config */
		'@phan-var SearchConfig $config';
		$connection = new Connection( $config );
		parent::__construct( $connection, $offset, $limit, $config, null, null, false, $options );
		$this->syntaxUsed = $syntaxUsed;
		$this->statsKey = $statsKey;
	}

	/**
	 * Build search query object.
	 * @return \Elastica\Search
	 */
	protected function buildSearch() {
		$this->searchContext->addSyntaxUsed( $this->syntaxUsed, PHP_INT_MAX );

		$indexSuffix = $this->connection->pickIndexSuffixForNamespaces( $this->getSearchContext()->getNamespaces() );
		$index = $this->connection->getIndex( $this->indexBaseName, $indexSuffix );

		$queryOptions = [
			\Elastica\Search::OPTION_TIMEOUT => $this->config->getElement( 'CirrusSearchSearchShardTimeout',
				'default' ),
		];
		$searchQuery = new Query();
		$searchQuery->setQuery( $this->query );
		$resultsType = $this->searchContext->getResultsType();
		$searchQuery->setSource( $resultsType->getSourceFiltering() );
		$searchQuery->setParam( 'fields', $resultsType->getFields() );

		$highlight = $this->searchContext->getHighlight( $resultsType, $this->query );
		if ( $highlight ) {
			$searchQuery->setHighlight( $highlight );
		}
		if ( $this->offset ) {
			$searchQuery->setFrom( $this->offset );
		}
		if ( $this->limit ) {
			$searchQuery->setSize( $this->limit );
		}
		$searchQuery->setParam( 'rescore', $this->searchContext->getRescore() );
		// Mark wikibase prefix searches for statistics
		$searchQuery->addParam( 'stats', $this->statsKey );
		$this->applyDebugOptionsToQuery( $searchQuery );
		return $index->createSearch( $searchQuery, $queryOptions );
	}

	/**
	 * Perform search for Wikibase entities.
	 * @param AbstractQuery $query Search query.
	 * @return Status
	 */
	public function performSearch( AbstractQuery $query ) {
		$this->query = $query;
		$status = $this->searchOne();

		// TODO: this probably needs to go to Searcher API.
		foreach ( $this->searchContext->getWarnings() as $warning ) {
			$status->warning( ...$warning );
		}

		return $status;
	}

	/**
	 * Add warning message about something in search.
	 * @param string $message i18n message key
	 * @param mixed ...$params
	 */
	public function addWarning( $message, ...$params ) {
		$this->searchContext->addWarning( $message, ...$params );
	}

}
