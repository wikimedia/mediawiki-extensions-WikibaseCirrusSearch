<?php

namespace Wikibase\Search\Elastic\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Query\FilterQueryFeature;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use DateTimeImmutable;
use DateTimeZone;
use Elastica\Query\AbstractQuery;
use Elastica\Query\Range;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Search\Elastic\Fields\StatementTimeField;

/**
 * Handles the search keyword 'wbstatementtime:'
 *
 * Allows the user to search for pages/items that have wikibase time-valued statements associated
 * with them, and specify date-based conditions on those statements.
 *
 * @uses \CirrusSearch
 */
class WbStatementTimeFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	private const MAX_CLAUSES = 10;
	private const MAX_DATE = '9999-12-31';
	private const MAX_TIMESTAMP = '9999-12-31T23:59:59Z';

	private readonly DateTimeImmutable $now;

	/**
	 * @param string[] $allowedPropertyIds
	 */
	public function __construct(
		private readonly array $allowedPropertyIds = [],
		?DateTimeImmutable $now = null
	) {
		$this->now = ( $now ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'wbstatementtime' ];
	}

	/**
	 * @param SearchContext $context
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param bool $negated
	 * @return array{AbstractQuery|null,bool}
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$params = $this->parseValue(
			$key,
			$value,
			$quotedValue,
			'',
			'',
			$context
		);
		if ( $params['clauses'] === [] ) {
			$context->setResultsPossible( false );
			return [ null, false ];
		}

		return [ $this->createFilters( $params ), false ];
	}

	/**
	 * @param array{clauses:array} $params
	 * @return AbstractQuery
	 */
	private function createFilters( array $params ): AbstractQuery {
		$filters = [];
		foreach ( $params['clauses'] as $clause ) {
			$filters[] = new Range(
				StatementTimeField::NAME . '.' . $clause['propertyId'],
				$clause['range']
			);
		}
		return Filters::booleanOr( $filters );
	}

	/**
	 * Dates are kept as DateTimeImmutable objects to avoid reparsing and keep
	 * timezone handling explicit when building range parameters.
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array{clauses:array}
	 */
	public function parseValue(
		$key,
		$value,
		$quotedValue,
		$valueDelimiter,
		$suffix,
		WarningCollector $warningCollector
	) {
		$clauses = [];
		$ignoredInvalidClause = false;
		$queryStrings = explode( '|', $value, self::MAX_CLAUSES + 1 );
		if ( count( $queryStrings ) > self::MAX_CLAUSES ) {
			$warningCollector->addWarning(
				'wikibasecirrus-wbstatementtime-feature-too-many-clauses',
				$key,
				self::MAX_CLAUSES
			);
			return [ 'clauses' => [] ];
		}

		foreach ( $queryStrings as $queryString ) {
			$queryParts = $this->parseQueryString( $queryString );
			if ( $queryParts === null ) {
				$ignoredInvalidClause = true;
				continue;
			}
			if ( !$this->isAllowedPropertyId( $queryParts['property'] ) ) {
				$warningCollector->addWarning(
					'wikibasecirrus-wbstatementtime-feature-property-not-indexed',
					$key,
					$queryParts['property']
				);
				continue;
			}

			$comparisons = [];
			foreach ( $queryParts['comparisons'] as $comparison ) {
				$date = $this->parseDate( $comparison['date'] );
				if ( $date === null ) {
					$ignoredInvalidClause = true;
					continue 2;
				}
				$comparisons[] = [
					'operator' => $comparison['operator'],
					'date' => $date,
				];
			}

			$range = $this->buildRange( $comparisons );
			if ( $range === null ) {
				$ignoredInvalidClause = true;
				continue;
			}
			$clauses[] = [
				'propertyId' => $queryParts['property'],
				'range' => $range,
			];
		}

		if ( $ignoredInvalidClause ) {
			$warningCollector->addWarning(
				'wikibasecirrus-wbstatementtime-feature-invalid-clause',
				$key
			);
		}
		if ( $clauses === [] ) {
			$warningCollector->addWarning(
				'wikibasecirrus-wbstatementtime-feature-no-valid-statements',
				$key
			);
		}

		return [
			'clauses' => $clauses,
		];
	}

	/**
	 * Parse a property followed by one or two date comparisons.
	 * Comparisons in the same clause are combined so one indexed value
	 * must satisfy both.
	 *
	 * @return array{property:string,comparisons:array}|null
	 */
	private function parseQueryString( string $queryString ): ?array {
		if ( !preg_match( '/^([Pp][1-9]\d*)(.*)$/', $queryString, $propertyParts ) ) {
			return null;
		}
		$property = strtoupper( $propertyParts[1] );
		if ( !$this->isValidPropertyId( $property ) ) {
			return null;
		}

		$comparisonString = $propertyParts[2];
		preg_match_all(
			'/(>=?|<=?|=)(\d{4}-\d{2}-\d{2}|today|now)/',
			$comparisonString,
			$comparisonParts,
			PREG_SET_ORDER
		);
		if ( count( $comparisonParts ) < 1 || count( $comparisonParts ) > 2 ||
			implode( '', array_column( $comparisonParts, 0 ) ) !== $comparisonString
		) {
			return null;
		}

		return [
			'property' => $property,
			'comparisons' => array_map(
				static fn ( array $parts ) => [
					'operator' => $parts[1],
					'date' => $parts[2],
				],
				$comparisonParts
			),
		];
	}

	private function isValidPropertyId( string $propertyId ): bool {
		return (bool)preg_match( NumericPropertyId::PATTERN, $propertyId );
	}

	private function isAllowedPropertyId( string $propertyId ): bool {
		return in_array( $propertyId, $this->allowedPropertyIds, true );
	}

	/**
	 * Parse a YYYY-MM-DD date in UTC.
	 *
	 * @return DateTimeImmutable|null Null for invalid dates.
	 */
	private function parseDate( string $dateString ): ?DateTimeImmutable {
		if ( $dateString === 'today' || $dateString === 'now' ) {
			return DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$this->now->format( 'Y-m-d' ),
				new DateTimeZone( 'UTC' )
			) ?: null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $dateString, new DateTimeZone( 'UTC' ) );
		if ( $date === false || $date->format( 'Y' ) === '0000' || $date->format( 'Y-m-d' ) !== $dateString ) {
			return null;
		}
		return $date;
	}

	/**
	 * Build range parameters for YYYY-MM-DD input.
	 *
	 * Date-only comparisons include all indexed times within the requested day:
	 * equality matches from the date through, but not including, the next day;
	 * <= also ends before the next day.
	 *
	 * @return array<string,string>|null
	 */
	private function buildRangeParameters( string $operator, DateTimeImmutable $date ): ?array {
		$dateString = $date->format( 'Y-m-d' );
		if ( $dateString === self::MAX_DATE ) {
			return match ( $operator ) {
				'=' => [ 'gte' => self::MAX_DATE, 'lte' => self::MAX_TIMESTAMP ],
				'>' => null,
				'>=' => [ 'gte' => self::MAX_DATE ],
				'<' => [ 'lt' => self::MAX_DATE ],
				'<=' => [ 'lte' => self::MAX_TIMESTAMP ],
			};
		}
		$nextDay = $date->modify( '+1 day' )->format( 'Y-m-d' );

		return match ( $operator ) {
			'=' => [ 'gte' => $dateString, 'lt' => $nextDay ],
			'>' => [ 'gte' => $nextDay ],
			'>=' => [ 'gte' => $dateString ],
			'<' => [ 'lt' => $dateString ],
			'<=' => [ 'lt' => $nextDay ],
		};
	}

	/**
	 * Merge comparisons into one Elasticsearch range. Duplicate bounds and
	 * ranges whose lower bound is not before the upper bound are invalid.
	 *
	 * @param array<int,array{operator:string,date:DateTimeImmutable}> $comparisons
	 * @return array<string,string>|null
	 */
	private function buildRange( array $comparisons ): ?array {
		if ( count( $comparisons ) > 1 &&
			array_filter( $comparisons, static fn ( array $comparison ) => $comparison['operator'] === '=' )
		) {
			return null;
		}

		$range = [];
		foreach ( $comparisons as $comparison ) {
			$parameters = $this->buildRangeParameters( $comparison['operator'], $comparison['date'] );
			if ( $parameters === null ) {
				return null;
			}
			$hasLowerBound = isset( $range['gte'] ) && isset( $parameters['gte'] );
			$hasUpperBound = ( isset( $range['lt'] ) || isset( $range['lte'] ) ) &&
				( isset( $parameters['lt'] ) || isset( $parameters['lte'] ) );
			if ( $hasLowerBound || $hasUpperBound ) {
				return null;
			}
			$range += $parameters;
		}

		if ( isset( $range['gte'] ) ) {
			if ( isset( $range['lt'] ) && $range['gte'] >= $range['lt'] ) {
				return null;
			}
			if ( isset( $range['lte'] ) && $range['gte'] > $range['lte'] ) {
				return null;
			}
		}
		return $range;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		$params = $node->getParsedValue();
		if ( $params === null || $params['clauses'] === [] ) {
			return null;
		}
		return $this->createFilters( $params );
	}

}
