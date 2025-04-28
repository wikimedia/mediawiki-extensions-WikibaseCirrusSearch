<?php declare( strict_types=1 );

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\FuzzyNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\PrefixNode;
use CirrusSearch\Parser\AST\Visitor\LeafVisitor;
use CirrusSearch\Parser\AST\WildcardNode;
use CirrusSearch\Parser\AST\WordsQueryNode;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchPhrase;
use Elastica\Query\MatchQuery;

/**
 * @license GPL-2.0-or-later
 */
class InLabelFilterVisitor extends LeafVisitor {
	private AbstractQuery $filterQuery;
	private string $field;

	public function __construct( string $field ) {
		parent::__construct();
		$this->field = $field;
		$this->filterQuery = new BoolQuery();
	}

	public function getFilterQuery(): AbstractQuery {
		return $this->filterQuery;
	}

	private function updateFilterQuery( AbstractQuery $query ) {
		if ( $this->getCurrentBooleanClause() ) {
			switch ( $this->getCurrentBooleanClause()->getOccur() ) {
				case BooleanClause::SHOULD:
					$this->filterQuery->addShould( $query );
					break;
				case BooleanClause::MUST:
					$this->filterQuery->addMust( $query );
					break;
				case BooleanClause::MUST_NOT:
					$this->filterQuery->addMustNot( $query );
					break;
			}
		} else {
			$this->filterQuery = $query;
		}
	}

	/** @inheritDoc */
	public function visitWordsQueryNode( WordsQueryNode $node ) {
		$matchQuery = new MatchQuery( $this->field, [ 'query' => $node->getWords() ] );
		$matchQuery->setFieldOperator( $this->field, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitPhraseQueryNode( PhraseQueryNode $node ) {
		$this->updateFilterQuery( new MatchPhrase( $this->field, $node->getPhrase() ) );
	}

	/** @inheritDoc	*/
	public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
		$this->updateFilterQuery( new MatchPhrase( $this->field, $node->getPhrase() ) );
	}

	/** @inheritDoc	*/
	public function visitFuzzyNode( FuzzyNode $node ) {
		$matchQuery = new MatchQuery( $this->field, [ 'query' => $node->getWord() ] );
		$matchQuery->setFieldOperator( $this->field, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitPrefixNode( PrefixNode $node ) {
		$matchQuery = new MatchQuery( $this->field, [ 'query' => $node->getPrefix() ] );
		$matchQuery->setFieldOperator( $this->field, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitWildcardNode( WildcardNode $node ) {
		$matchQuery = new MatchQuery( $this->field, [ 'query' => $node->getWildcardQuery() ] );
		$matchQuery->setFieldOperator( $this->field, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitEmptyQueryNode( EmptyQueryNode $node ) {
	}

	/** @inheritDoc	*/
	public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
	}
}
