<?php

namespace Wikibase\Search\Elastic\Fields;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Search\CirrusIndexField;
use DataValues\TimeValue;
use InvalidArgumentException;
use MediaWiki\Search\SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * Field for indexing TimeValue statement values to enable date-based filtering.
 *
 * This field stores properties with a time datatype from the configured
 * statement property list as date subfields to support range queries such as
 * wbstatementtime:P571>=2025-01-01.
 *
 * It follows Wikibase best-statement semantics. Preferred statements are
 * indexed when present for a property, otherwise normal statements are indexed.
 * Deprecated statements are not indexed.
 *
 * The field supports Gregorian time values with at least day precision
 * and years from 0001 through 9999.
 *
 * Invalid dates such as February 30 are rejected rather than repaired,
 * unlike the RDF export (see \Wikibase\Repo\Rdf\DateTimeValueCleaner),
 * which clamps them so WDQS can index something for every value; in a
 * search filter a fabricated date is worse than none.
 *
 * If Julian or sub-day precision support is added, it should follow the
 * DateTimeValueCleaner / JulianDateTimeValueCleaner semantics
 * (Julian dates converted to Gregorian, imprecise dates rounded down)
 * so that results stay consistent with WDQS.
 */
class StatementTimeField extends StatementsField implements WikibaseIndexField {

	public const NAME = 'statement_time';

	/**
	 * Returns normalized time values keyed by property ID.
	 *
	 * The values must have at least a precision of a day.
	 *
	 * Day-precision values are formatted as YYYY-MM-DD, and more precise values
	 * keep their full timestamp.
	 *
	 * @param EntityDocument $entity
	 * @return array<string,string[]>
	 */
	public function getFieldData( EntityDocument $entity ) {
		$data = [];
		$statements = new StatementList();

		foreach ( $this->getStatements( $entity ) as $statement ) {
			if ( !$this->isConfiguredPropertyStatement( $statement ) ) {
				continue;
			}

			$statements->addStatement( $statement );
		}

		foreach ( $statements->getPropertyIds() as $propertyId ) {
			foreach ( $statements->getByPropertyId( $propertyId )->getBestStatements() as $statement ) {
				$timeValue = $this->getTimeValueFromStatement( $statement );
				if ( $timeValue === null ) {
					continue;
				}
				if ( !$this->shouldIndexTimeValue( $timeValue ) ) {
					continue;
				}

				$time = $this->normalizeTimeValue( $timeValue );
				if ( $time !== null ) {
					$data[$propertyId->getSerialization()][] = $time;
				}
			}
		}

		return $data;
	}

	/**
	 * Return true for statements whose property has a mapped statement_time subfield.
	 */
	private function isConfiguredPropertyStatement( Statement $statement ): bool {
		return array_key_exists(
			$statement->getPropertyId()->getSerialization(),
			$this->propertyIds
		);
	}

	/**
	 * Return the statement's TimeValue when it has the expected time datatype.
	 *
	 * @param Statement $statement
	 * @return TimeValue|null
	 */
	private function getTimeValueFromStatement( Statement $statement ): ?TimeValue {
		$snak = $statement->getMainSnak();
		if ( !( $snak instanceof PropertyValueSnak ) ) {
			return null;
		}

		$propType = $this->getWhitelistedPropType( $snak, $statement->getGuid() );
		if ( $propType === null ) {
			return null;
		}

		if ( $propType !== 'time' ) {
			return null;
		}

		$dataValue = $snak->getDataValue();
		if ( !( $dataValue instanceof TimeValue ) ) {
			return null;
		}

		return $dataValue;
	}

	/**
	 * Supports only Gregorian dates with day precision or greater
	 */
	private function shouldIndexTimeValue( TimeValue $timeValue ): bool {
		return $timeValue->getPrecision() >= TimeValue::PRECISION_DAY &&
			$timeValue->getCalendarModel() === TimeValue::CALENDAR_GREGORIAN;
	}

	/**
	 * Normalize Wikibase time strings to Elasticsearch-compatible dates.
	 *
	 * This supports day-precision values between 0001-01-01 and 9999-12-31.
	 * Timezone offsets are intentionally ignored; the serialized calendar value is indexed as written.
	 *
	 * @return string|null Null for invalid or out of range dates.
	 */
	private function normalizeTimeValue( TimeValue $timeValue ): ?string {
		$timeValueParts = $this->extractTimeValueParts( $timeValue );
		if ( $timeValueParts === null ) {
			return null;
		}

		[ $year, $month, $day, $timePart ] = $timeValueParts;
		if ( !checkdate( (int)$month, (int)$day, (int)$year ) ) {
			return null;
		}

		$time = "$year-$month-$day";

		if ( $timeValue->getPrecision() === TimeValue::PRECISION_DAY ) {
			return $time;
		}

		// exclude time values with leap seconds
		if ( !preg_match( '/^\d{2}:\d{2}:[0-5]\dZ$/', $timePart ) ) {
			return null;
		}

		return $time . 'T' . $timePart;
	}

	/**
	 * @return string[]|null Four-element array with year, month, day, and time parts.
	 */
	private function extractTimeValueParts( TimeValue $timeValue ): ?array {
		if ( !preg_match(
			'/^\+(\d+)-(\d{2})-(\d{2})T(.+)$/',
			$timeValue->getTime(),
			$timeValueParts
		) ) {
			return null;
		}

		$year = (int)$timeValueParts[1];
		if ( $year < 1 || $year > 9999 ) {
			return null;
		}

		return [
			str_pad( (string)$year, 4, '0', STR_PAD_LEFT ),
			$timeValueParts[2],
			$timeValueParts[3],
			$timeValueParts[4],
		];
	}

	/**
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getMapping( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			return [];
		}

		$mapping = [
			'type' => 'object',
			'dynamic' => false,
		];
		foreach ( $this->getTimePropertyIds() as $propertyId ) {
			$mapping['properties'][$propertyId] = [
				'type' => 'date',
				'format' => 'date_optional_time'
			];
		}
		return $mapping;
	}

	/**
	 * @return string[]
	 */
	private function getTimePropertyIds(): array {
		return array_keys( array_filter(
			$this->propertyIds,
			function ( string $propertyId ) {
				try {
					return $this->getDataTypeIdForProperty( new NumericPropertyId( $propertyId ) ) === 'time';
				} catch ( InvalidArgumentException ) {
					return false;
				} catch ( PropertyDataTypeLookupException $exception ) {
					$this->getLogger()->warning(
						self::class . '::getTimePropertyIds: Configured property {propertyId} ' .
							'for {fieldName} could not be found',
						[
							'propertyId' => $propertyId,
							'fieldName' => static::NAME,
							'exception' => $exception,
						]
					);
					return true;
				}
			},
			ARRAY_FILTER_USE_KEY
		) );
	}

	/**
	 * Compare the full property map on updates so removed time properties are
	 * reflected in the indexed document.
	 *
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getEngineHints( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			return [];
		}
		return [ CirrusIndexField::NOOP_HINT => 'equals' ];
	}

}
