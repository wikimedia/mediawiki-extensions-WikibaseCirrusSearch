<?php
namespace Wikibase\Search\Elastic;

use Elastica\Query\ConstantScore;
use Elastica\Query\MatchQuery;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * Utilities useful for entity searches.
 */
final class EntitySearchUtils {

	/**
	 * Create constant score query for a field.
	 * @param string $field
	 * @param string|double $boost
	 * @param string $text
	 * @param string $matchOperator
	 * @return ConstantScore
	 */
	public static function makeConstScoreQuery( $field, $boost, $text, $matchOperator = MatchQuery::OPERATOR_OR ) {
		if ( $matchOperator === MatchQuery::OPERATOR_AND ) {
			$filter = new MatchQuery( $field, [ 'query' => $text ] );
			$filter->setFieldOperator( $field, $matchOperator );
		} else {
			$filter = new MatchQuery( $field, $text );
		}

		$csquery = new ConstantScore();
		$csquery->setFilter( $filter );
		$csquery->setBoost( $boost );
		return $csquery;
	}

	/**
	 * If the text looks like ID, normalize it to ID title
	 * Cases handled:
	 * - q42
	 * - (q42)
	 * - leading/trailing spaces
	 * - http://www.wikidata.org/entity/Q42
	 * @param string $text
	 * @param EntityIdParser $idParser
	 * @return string Normalized ID or original string
	 */
	public static function normalizeId( $text, EntityIdParser $idParser ) {
		return self::normalizeIdFromSearchQuery( $text, self::entityIdParserNormalizer( $idParser ) );
	}

	/**
	 * If the text looks like ID, normalize it to ID title
	 * Cases handled:
	 * - q42
	 * - (q42)
	 * - leading/trailing spaces
	 * - http://www.wikidata.org/entity/Q42
	 * @param string $text
	 * @param callable(string):(string|null) $idNormalizer
	 * @return string Normalized ID or original string
	 */
	public static function normalizeIdFromSearchQuery( $text, callable $idNormalizer ) {
		// TODO: this is a bit hacky, better way would be to make the field case-insensitive
		// or add new subfiled which is case-insensitive
		$text = strtoupper( str_replace( [ '(', ')' ], '', trim( $text ) ) );
		$id = $idNormalizer( $text );
		if ( $id !== null ) {
			return $id;
		}
		if ( preg_match( '/\b(\w+)$/', $text, $matches ) && $matches[1] ) {
			$id = $idNormalizer( $matches[1] );
			if ( $id !== null ) {
				return $id;
			}
		}
		return $text;
	}

	/**
	 * Parse entity ID or return null
	 * @param string $text
	 * @param EntityIdParser $idParser
	 * @return ?EntityId
	 */
	public static function parseOrNull( $text, EntityIdParser $idParser ): ?EntityId {
		try {
			$id = $idParser->parse( $text );
		} catch ( EntityIdParsingException ) {
			return null;
		}
		return $id;
	}

	/**
	 * An id normalizer based on a given EntityIdParser.
	 *
	 * @param EntityIdParser $idParser
	 * @return callable(string):(string|null)
	 */
	public static function entityIdParserNormalizer( EntityIdParser $idParser ): callable {
		return static function ( string $text ) use ( $idParser ): ?string {
			$id = EntitySearchUtils::parseOrNull( $text, $idParser );
			if ( $id === null ) {
				return null;
			}
			return $id->getSerialization();
		};
	}

	/**
	 * Locate label for display among the source data, basing on fallback chain.
	 * @param array $sourceData
	 * @param string $field
	 * @param TermLanguageFallbackChain $termFallbackChain
	 * @return null|Term
	 */
	public static function findTermForDisplay( $sourceData, $field, TermLanguageFallbackChain $termFallbackChain ) {
		if ( empty( $sourceData[$field] ) ) {
			return null;
		}

		$data = $sourceData[$field];
		$first = reset( $data );
		if ( is_array( $first ) ) {
			// If we have multiple, like for labels, extract the first one
			$labels_data = array_map(
				static function ( $data ) {
					return $data[0] ?? null;
				},
				$data
			);
		} else {
			$labels_data = $data;
		}
		// Drop empty ones
		$labels_data = array_filter( $labels_data );

		$preferredValue = $termFallbackChain->extractPreferredValueOrAny( $labels_data );
		if ( $preferredValue ) {
			return new Term( $preferredValue['language'], $preferredValue['value'] );
		}

		return null;
	}

}
