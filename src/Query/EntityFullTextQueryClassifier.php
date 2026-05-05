<?php

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\FuzzyNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\PrefixNode;
use CirrusSearch\Parser\AST\Visitor\LeafVisitor;
use CirrusSearch\Parser\AST\WildcardNode;
use CirrusSearch\Parser\AST\WordsQueryNode;
use CirrusSearch\Parser\ParsedQueryClassifier;

/**
 * Query classifier for Wikibase entity fulltext searches.
 *
 * Tags queries as ENTITY_FULL_TEXT when they contain simple words
 * (bag of words) and optionally Wikibase-owned keyword features,
 * but no unsupported syntax like phrases, wildcards, fuzzy, prefix,
 * negation, or non-Wikibase keywords.
 *
 * This allows queries like "Robert Cranford haswbstatement:P31=Q5" to be
 * routed through the Wikibase entity search pipeline, where
 * EntityFullTextQueryBuilder strips keywords via KeywordFeature::apply()
 * before building the entity query (T425253).
 *
 * @see MediaSearchASTClassifier for the pattern this follows
 * @see T425253
 * @license GPL-2.0-or-later
 */
class EntityFullTextQueryClassifier extends LeafVisitor implements ParsedQueryClassifier {

	public const ENTITY_FULL_TEXT = 'wikibase_entity_full_text';

	/** @var bool */
	private $hasWords = false;

	/** @var bool */
	private $hasUnsupported = false;

	/** @inheritDoc */
	public function classify( ParsedQuery $query ) {
		$this->hasWords = false;
		$this->hasUnsupported = false;
		$query->getRoot()->accept( $this );
		if ( $this->hasWords && !$this->hasUnsupported ) {
			return [ self::ENTITY_FULL_TEXT ];
		}
		return [];
	}

	/** @inheritDoc */
	public function classes() {
		return [ self::ENTITY_FULL_TEXT ];
	}

	/** @inheritDoc */
	public function visitWordsQueryNode( WordsQueryNode $node ) {
		$clause = $this->getCurrentBooleanClause();
		if ( $this->negated() || ( $clause !== null && $clause->isExplicit() ) ) {
			$this->hasUnsupported = true;
		} else {
			$this->hasWords = true;
		}
	}

	/** @inheritDoc */
	public function visitPhraseQueryNode( PhraseQueryNode $node ) {
		// Phrases are not yet supported by EntityFullTextQueryBuilder,
		// which expects a simple bag of words.
		$this->hasUnsupported = true;
	}

	/** @inheritDoc */
	public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
		// Only accept keyword features owned by WikibaseCirrusSearch.
		// Non-Wikibase keywords (e.g. insource, boost-templates) indicate
		// queries that should fall through to the default CirrusSearch pipeline.
		$keyword = $node->getKeyword();
		$class = get_class( $keyword );
		if ( !str_starts_with( $class, 'Wikibase\\' ) ) {
			$this->hasUnsupported = true;
		}
	}

	/** @inheritDoc */
	public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
		$this->hasUnsupported = true;
	}

	/** @inheritDoc */
	public function visitFuzzyNode( FuzzyNode $node ) {
		$this->hasUnsupported = true;
	}

	/** @inheritDoc */
	public function visitPrefixNode( PrefixNode $node ) {
		$this->hasUnsupported = true;
	}

	/** @inheritDoc */
	public function visitWildcardNode( WildcardNode $node ) {
		$this->hasUnsupported = true;
	}

	/** @inheritDoc */
	public function visitEmptyQueryNode( EmptyQueryNode $node ) {
		// not relevant
	}

}
