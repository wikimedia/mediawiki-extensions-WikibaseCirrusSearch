<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic;

use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * This result type implements the result for searching
 * a Wikibase entity by its label or alias.
 *
 * (This class is not directly related to EntityResult.)
 *
 * @license GPL-2.0-or-later
 */
class EntityElasticTermResult extends ElasticTermResult {

	private EntityIdParser $idParser;

	public function __construct(
		EntityIdParser $idParser,
		array $searchLanguageCodes,
		string $highlightSubField,
		TermLanguageFallbackChain $displayFallbackChain
	) {
		parent::__construct( $searchLanguageCodes, $displayFallbackChain, $highlightSubField );
		$this->idParser = $idParser;
	}

	protected function getTermSearchResult(
		array $sourceData,
		Term $matchedTerm,
		string $matchedTermType,
		?Term $displayLabel,
		?Term $displayDescription
	): ?TermSearchResult {
		try {
			$entityId = $this->idParser->parse( $sourceData['title'] );
		} catch ( EntityIdParsingException $e ) {
			// Can not parse entity ID - skip it
			return null;
		}

		return new TermSearchResult(
			$matchedTerm, $matchedTermType, $entityId, $displayLabel,
			$displayDescription
		);
	}

}
