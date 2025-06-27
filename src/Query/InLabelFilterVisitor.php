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
use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Fields\LabelsField;

/**
 * @license GPL-2.0-or-later
 */
class InLabelFilterVisitor extends LeafVisitor {
	private const ALL_LABELS_FIELD = AllLabelsField::NAME . '.plain';

	private AbstractQuery $filterQuery;
	private string $languageCode;
	private array $stemmingSettings;

	public function __construct( string $languageCode, array $stemmingSettings ) {
		parent::__construct();
		$this->filterQuery = new BoolQuery();
		$this->languageCode = $languageCode;
		$this->stemmingSettings = $stemmingSettings;
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
		$filter = new BoolQuery();
		$filter->setMinimumShouldMatch( 1 );

		$matchQuery = new MatchQuery( self::ALL_LABELS_FIELD, [ 'query' => $node->getWords() ] );
		$matchQuery->setFieldOperator( self::ALL_LABELS_FIELD, MatchQuery::OPERATOR_AND );
		$filter->addShould( $matchQuery );

		if ( !empty( $this->stemmingSettings[$this->languageCode]['query'] ) ) {
			$stemFilter = new MatchQuery( LabelsField::NAME . '.' . $this->languageCode, [ 'query' => $node->getWords() ] );
			$stemFilter->setFieldOperator( LabelsField::NAME . '.' . $this->languageCode, MatchQuery::OPERATOR_AND );
			$filter->addShould( $stemFilter );
		}

		$this->updateFilterQuery( $filter );
	}

	/** @inheritDoc	*/
	public function visitPhraseQueryNode( PhraseQueryNode $node ) {
		$this->updateFilterQuery( new MatchPhrase( self::ALL_LABELS_FIELD, $node->getPhrase() ) );
	}

	/** @inheritDoc	*/
	public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
		$this->updateFilterQuery( new MatchPhrase( self::ALL_LABELS_FIELD, $node->getPhrase() ) );
	}

	/** @inheritDoc	*/
	public function visitFuzzyNode( FuzzyNode $node ) {
		$matchQuery = new MatchQuery( self::ALL_LABELS_FIELD, [ 'query' => $node->getWord() ] );
		$matchQuery->setFieldOperator( self::ALL_LABELS_FIELD, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitPrefixNode( PrefixNode $node ) {
		$matchQuery = new MatchQuery( self::ALL_LABELS_FIELD, [ 'query' => $node->getPrefix() ] );
		$matchQuery->setFieldOperator( self::ALL_LABELS_FIELD, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitWildcardNode( WildcardNode $node ) {
		$matchQuery = new MatchQuery( self::ALL_LABELS_FIELD, [ 'query' => $node->getWildcardQuery() ] );
		$matchQuery->setFieldOperator( self::ALL_LABELS_FIELD, MatchQuery::OPERATOR_AND );
		$this->updateFilterQuery( $matchQuery );
	}

	/** @inheritDoc	*/
	public function visitEmptyQueryNode( EmptyQueryNode $node ) {
	}

	/** @inheritDoc	*/
	public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
	}
}
