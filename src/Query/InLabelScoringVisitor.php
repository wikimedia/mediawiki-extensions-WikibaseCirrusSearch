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
use Elastica\Query\DisMax;
use Wikibase\Search\Elastic\EntitySearchUtils;
use Wikibase\Search\Elastic\Fields\LabelsField;

/**
 * @license GPL-2.0-or-later
 */
class InLabelScoringVisitor extends LeafVisitor {

	private array $nonNegatedWords = [];
	private bool $containsPhrase = false;

	public function __construct() {
		parent::__construct( [ BooleanClause::MUST_NOT ] );
	}

	public function buildScoringQuery( array $languageCodes, array $profile ): DisMax {
		$labelsName = LabelsField::NAME;
		$text = implode( ' ', $this->nonNegatedWords );
		// TODO: Should this be a DisMax or a BoolQuery?
		$dismax = new DisMax();
		$dismax->setTieBreaker( $profile['tie-breaker'] );
		foreach ( $languageCodes as $languageCode ) {
			if ( !$this->containsPhrase ) {
				// TODO: Using near match on more complex boolean queries is up for debate
				$dismax->addQuery( EntitySearchUtils::makeConstScoreQuery(
					"$labelsName.$languageCode.near_match",
					$profile["$languageCode-exact"],
					$text
				) );
				$dismax->addQuery( EntitySearchUtils::makeConstScoreQuery(
					"$labelsName.$languageCode.near_match_folded",
					$profile["$languageCode-folded"],
					$text
				) );
			}

			$dismax->addQuery( EntitySearchUtils::makeConstScoreQuery(
				"$labelsName.$languageCode.plain",
				$profile["$languageCode-tokenized"],
				$text
			) );

			// TODO: Should we also add a 'labels_all' field using $profile['any']?
			//       Which type(s)? '.plain' / '.near_match' / '.near_match_folded'?
		}

		return $dismax;
	}

	/** @inheritDoc	*/
	public function visitWordsQueryNode( WordsQueryNode $node ) {
		$this->nonNegatedWords[] = $node->getWords();
	}

	/** @inheritDoc	*/
	public function visitPhraseQueryNode( PhraseQueryNode $node ) {
		// TODO: Ok to blend phrases with the other query words, or should they be scored specifically with a MatchPhrase?
		$this->containsPhrase = true;
		$this->nonNegatedWords[] = $node->getPhrase();
	}

	/** @inheritDoc	*/
	public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
		$this->containsPhrase = true;
		$this->nonNegatedWords[] = $node->getPhrase();
	}

	/** @inheritDoc	*/
	public function visitFuzzyNode( FuzzyNode $node ) {
		$this->nonNegatedWords[] = $node->getWord();
	}

	/** @inheritDoc	*/
	public function visitPrefixNode( PrefixNode $node ) {
		$this->nonNegatedWords[] = $node->getPrefix();
	}

	/** @inheritDoc	*/
	public function visitWildcardNode( WildcardNode $node ) {
		$this->nonNegatedWords[] = $node->getWildcardQuery();
	}

	/** @inheritDoc	*/
	public function visitEmptyQueryNode( EmptyQueryNode $node ) {
	}

	/** @inheritDoc	*/
	public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
	}
}
