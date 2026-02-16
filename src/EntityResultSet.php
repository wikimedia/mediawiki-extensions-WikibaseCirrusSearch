<?php
namespace Wikibase\Search\Elastic;

use CirrusSearch\Search\BaseCirrusSearchResultSet;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * Result set for entity search
 */
class EntityResultSet extends BaseCirrusSearchResultSet {

	public function __construct(
		private readonly string $displayLanguage,
		private readonly TermLanguageFallbackChain $termFallbackChain,
		private readonly \Elastica\ResultSet $result,
	) {
	}

	/** @inheritDoc */
	protected function transformOneResult( \Elastica\Result $result ) {
		return new EntityResult( $this->displayLanguage, $this->termFallbackChain, $result );
	}

	/**
	 * @return \Elastica\ResultSet
	 */
	public function getElasticaResultSet() {
		return $this->result;
	}

	/**
	 * Did the search contain search syntax?  If so, Special:Search won't offer
	 * the user a link to a create a page named by the search string because the
	 * name would contain the search syntax.
	 * @return bool
	 */
	public function searchContainedSyntax() {
		return false;
	}

}
