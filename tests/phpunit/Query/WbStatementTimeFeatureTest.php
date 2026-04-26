<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use DateTimeImmutable;
use DateTimeZone;
use Wikibase\Search\Elastic\Query\WbStatementTimeFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\WbStatementTimeFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class WbStatementTimeFeatureTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
	}

	public static function applyProvider() {
		return [
			'single statement with equals' => [
				'expected' => [
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-01',
						'lt' => '2020-01-02',
					] ],
				],
				'term' => 'wbstatementtime:P580=2020-01-01',
			],
			'single statement with greater than' => [
				'expected' => [
					// A date-only ">" comparison starts at the next whole day.
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-02',
					] ],
				],
				'term' => 'wbstatementtime:P580>2020-01-01',
			],
			'single statement with greater than or equals' => [
				'expected' => [
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-01',
					] ],
				],
				'term' => 'wbstatementtime:P580>=2020-01-01',
			],
			'lowercase property ID' => [
				'expected' => [
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-01',
					] ],
				],
				'term' => 'wbstatementtime:p580>=2020-01-01',
			],
			'single statement with less than' => [
				'expected' => [
					'range' => [ 'statement_time.P582' => [
						'lt' => '2021-01-01',
					] ],
				],
				'term' => 'wbstatementtime:P582<2021-01-01',
			],
			'single statement with less than or equals' => [
				'expected' => [
					'range' => [ 'statement_time.P582' => [
						'lt' => '2021-01-02',
					] ],
				],
				'term' => 'wbstatementtime:P582<=2021-01-01',
			],
			'equals maximum date' => [
				'expected' => [
					'range' => [ 'statement_time.P571' => [
						'gte' => '9999-12-31',
						'lte' => '9999-12-31T23:59:59Z',
					] ],
				],
				'term' => 'wbstatementtime:P571=9999-12-31',
			],
			'less than or equals maximum date' => [
				'expected' => [
					'range' => [ 'statement_time.P571' => [
						'lte' => '9999-12-31T23:59:59Z',
					] ],
				],
				'term' => 'wbstatementtime:P571<=9999-12-31',
			],
			'multiple statements' => [
				'expected' => [
					'bool' => [
						'minimum_should_match' => 1,
						'should' => [
							[
								'range' => [ 'statement_time.P580' => [
									'gte' => '2020-01-01',
									'lt' => '2020-01-02',
								] ],
							],
							[
								'range' => [ 'statement_time.P582' => [
									'lt' => '2021-01-01',
								] ],
							],
						],
					],
				],
				'term' => 'wbstatementtime:P580=2020-01-01|P582<2021-01-01',
			],
			'bounded range' => [
				'expected' => [
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-01',
						'lt' => '2021-01-01',
					] ],
				],
				'term' => 'wbstatementtime:P580>=2020-01-01<2021-01-01',
			],
			'multiple bounded ranges' => [
				'expected' => [
					'bool' => [
						'minimum_should_match' => 1,
						'should' => [
							[
								'range' => [ 'statement_time.P580' => [
									'gte' => '2020-01-01',
									'lt' => '2021-01-01',
								] ],
							],
							[
								'range' => [ 'statement_time.P580' => [
									'gte' => '2023-01-01',
									'lt' => '2024-01-01',
								] ],
							],
						],
					],
				],
				'term' => 'wbstatementtime:P580>=2020-01-01<2021-01-01|' .
					'P580>=2023-01-01<2024-01-01',
			],
			'some data invalid' => [
				'expected' => [
					'range' => [ 'statement_time.P580' => [
						'gte' => '2020-01-01',
						'lt' => '2020-01-02',
					] ],
				],
				'term' => 'wbstatementtime:INVALID|P580=2020-01-01',
				'warnings' => [
					[ 'wikibasecirrus-wbstatementtime-feature-invalid-clause', 'wbstatementtime' ],
				],
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( $expected, $term, $warnings = [] ) {
		$feature = $this->newFeature();
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertFilter( $feature, $term, $expected, $warnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
	}

	public function testApplyRelativeDate() {
		$feature = $this->newFeature( self::newDate( '2026-07-03' ) );
		$expected = [
			'range' => [ 'statement_time.P571' => [
				'gte' => '2026-07-03',
				'lt' => '2026-07-04',
			] ],
		];

		$this->getKWAssertions()->assertFilter( $feature, 'wbstatementtime:P571=now', $expected, [] );
	}

	public static function applyNoDataProvider() {
		return [
			'empty data' => [
				'wbstatementtime:',
			],
			'no data' => [
				'',
			],
		];
	}

	/**
	 * @dataProvider applyNoDataProvider
	 */
	public function testNotConsumed( $term ) {
		$feature = new WbStatementTimeFeature();
		$this->getKWAssertions()->assertNotConsumed( $feature, $term );
	}

	public function testInvalidStatementWarning() {
		$feature = $this->newFeature();
		$expectedWarnings = [
			[ 'wikibasecirrus-wbstatementtime-feature-invalid-clause', 'wbstatementtime' ],
			[ 'wikibasecirrus-wbstatementtime-feature-no-valid-statements', 'wbstatementtime' ],
		];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue(
			$feature,
			'wbstatementtime:INVALID',
			[ 'clauses' => [] ],
			$expectedWarnings
		);
		$kwAssertions->assertExpandedData( $feature, 'wbstatementtime:INVALID', [], [] );
		$kwAssertions->assertFilter( $feature, 'wbstatementtime:INVALID', null, $expectedWarnings );
		$kwAssertions->assertNoResultsPossible( $feature, 'wbstatementtime:INVALID' );
	}

	public function testDisallowedPropertyWarning() {
		$feature = new WbStatementTimeFeature( [ 'P580' ] );
		$expectedWarnings = [
			[ 'wikibasecirrus-wbstatementtime-feature-property-not-indexed', 'wbstatementtime', 'P571' ],
			[ 'wikibasecirrus-wbstatementtime-feature-no-valid-statements', 'wbstatementtime' ],
		];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue(
			$feature,
			'wbstatementtime:P571=2020-01-01',
			[ 'clauses' => [] ],
			$expectedWarnings
		);
		$kwAssertions->assertFilter( $feature, 'wbstatementtime:P571=2020-01-01', null, $expectedWarnings );
		$kwAssertions->assertNoResultsPossible( $feature, 'wbstatementtime:P571=2020-01-01' );
	}

	public function testTooManyClausesWarning() {
		$feature = $this->newFeature();
		$term = 'wbstatementtime:' . implode( '|', array_fill( 0, 11, 'P571>=2020-01-01' ) );
		$expectedWarnings = [
			[ 'wikibasecirrus-wbstatementtime-feature-too-many-clauses', 'wbstatementtime', 10 ],
		];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue(
			$feature,
			$term,
			[ 'clauses' => [] ],
			$expectedWarnings
		);
		$kwAssertions->assertFilter( $feature, $term, null, $expectedWarnings );
		$kwAssertions->assertNoResultsPossible( $feature, $term );
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParseValue( $value, $expected, $warningExpected, $partialWarningExpected = false ) {
		$feature = $this->newFeature();
		$expectedWarnings = [];
		if ( $warningExpected || $partialWarningExpected ) {
			$expectedWarnings[] = [
				'wikibasecirrus-wbstatementtime-feature-invalid-clause',
				'wbstatementtime',
			];
		}
		if ( $warningExpected ) {
			$expectedWarnings[] = [
				'wikibasecirrus-wbstatementtime-feature-no-valid-statements',
				'wbstatementtime',
			];
		}
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue( $feature, "wbstatementtime:\"$value\"", $expected, $expectedWarnings );
	}

	/**
	 * @dataProvider relativeDateProvider
	 */
	public function testParseRelativeDate( $dateKeyword ) {
		$feature = $this->newFeature( self::newDate( '2026-07-03' ) );
		$expected = [
			'clauses' => [
				[
					'propertyId' => 'P571',
					'range' => [
						'gte' => '2026-07-03',
						'lt' => '2026-07-04',
					],
				],
			],
		];

		$this->getKWAssertions()->assertParsedValue(
			$feature,
			"wbstatementtime:P571=$dateKeyword",
			$expected,
			[]
		);
	}

	public static function relativeDateProvider() {
		return [
			'today' => [ 'today' ],
			'now' => [ 'now' ],
		];
	}

	public static function parseProvider() {
		return [
			'empty value' => [
				'value' => '',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'invalid property id' => [
				'value' => 'xyz>=2020-01-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'invalid operator' => [
				'value' => 'P580!=2020-01-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'invalid date format' => [
				'value' => 'P580>=2020/01/01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'invalid calendar date' => [
				'value' => 'P580>=2020-02-30',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'year zero' => [
				'value' => 'P580=0000-01-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'single value equals' => [
				'value' => 'P580=2020-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P580', [
						'gte' => '2020-01-01',
						'lt' => '2020-01-02',
					] ) ],
				],
				'warningExpected' => false,
			],
			'single value greater than' => [
				'value' => 'P580>2020-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P580', [ 'gte' => '2020-01-02' ] ) ],
				],
				'warningExpected' => false,
			],
			'single value greater than or equals' => [
				'value' => 'P580>=2020-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P580', [ 'gte' => '2020-01-01' ] ) ],
				],
				'warningExpected' => false,
			],
			'single value less than' => [
				'value' => 'P582<2021-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P582', [ 'lt' => '2021-01-01' ] ) ],
				],
				'warningExpected' => false,
			],
			'single value less than or equals' => [
				'value' => 'P582<=2021-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P582', [ 'lt' => '2021-01-02' ] ) ],
				],
				'warningExpected' => false,
			],
			'equals maximum date' => [
				'value' => 'P571=9999-12-31',
				'expected' => [
					'clauses' => [ self::newClause( 'P571', [
						'gte' => '9999-12-31',
						'lte' => '9999-12-31T23:59:59Z',
					] ) ],
				],
				'warningExpected' => false,
			],
			'greater than maximum date' => [
				'value' => 'P571>9999-12-31',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'less than or equals maximum date' => [
				'value' => 'P571<=9999-12-31',
				'expected' => [
					'clauses' => [ self::newClause( 'P571', [
						'lte' => '9999-12-31T23:59:59Z',
					] ) ],
				],
				'warningExpected' => false,
			],
			'bounded range ending at maximum date' => [
				'value' => 'P571>=9999-01-01<=9999-12-31',
				'expected' => [
					'clauses' => [ self::newClause( 'P571', [
						'gte' => '9999-01-01',
						'lte' => '9999-12-31T23:59:59Z',
					] ) ],
				],
				'warningExpected' => false,
			],
			'bounded range' => [
				'value' => 'P580>=2020-01-01<2021-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P580', [
						'gte' => '2020-01-01',
						'lt' => '2021-01-01',
					] ) ],
				],
				'warningExpected' => false,
			],
			'bounded range in reverse order' => [
				'value' => 'P580<2021-01-01>=2020-01-01',
				'expected' => [
					'clauses' => [ self::newClause( 'P580', [
						'lt' => '2021-01-01',
						'gte' => '2020-01-01',
					] ) ],
				],
				'warningExpected' => false,
			],
			'duplicate lower bound' => [
				'value' => 'P580>=2020-01-01>2020-02-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'equality combined with another comparison' => [
				'value' => 'P580=2020-01-01<2021-01-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'contradictory bounds' => [
				'value' => 'P580>=2021-01-01<2020-01-01',
				'expected' => [ 'clauses' => [] ],
				'warningExpected' => true,
			],
			'multiple values' => [
				'value' => 'P580>=2020-01-01|P582<2021-01-01',
				'expected' => [
					'clauses' => [
						self::newClause( 'P580', [ 'gte' => '2020-01-01' ] ),
						self::newClause( 'P582', [ 'lt' => '2021-01-01' ] ),
					],
				],
				'warningExpected' => false,
			],
			'lowercase property ID' => [
				'value' => 'P580=2020-01-01|p582<2021-01-01',
				'expected' => [
					'clauses' => [
						self::newClause( 'P580', [
							'gte' => '2020-01-01',
							'lt' => '2020-01-02',
						] ),
						self::newClause( 'P582', [ 'lt' => '2021-01-01' ] ),
					],
				],
				'warningExpected' => false,
			],
			'invalid range with valid alternative' => [
				'value' => 'P580>=2020-01-01>2021-01-01|P582<2020-01-01',
				'expected' => [
					'clauses' => [
						self::newClause( 'P582', [ 'lt' => '2020-01-01' ] ),
					],
				],
				'warningExpected' => false,
				'partialWarningExpected' => true,
			],
		];
	}

	private static function newDate( string $date ): DateTimeImmutable {
		return new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) );
	}

	private static function newClause( string $propertyId, array $range ): array {
		return [
			'propertyId' => $propertyId,
			'range' => $range,
		];
	}

	private function newFeature( ?DateTimeImmutable $now = null ): WbStatementTimeFeature {
		return new WbStatementTimeFeature( [ 'P571', 'P580', 'P582' ], $now );
	}

	private function getKWAssertions() {
		return new KeywordFeatureAssertions( $this );
	}

}
