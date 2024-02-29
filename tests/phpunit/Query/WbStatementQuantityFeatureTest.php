<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use Wikibase\Search\Elastic\Query\WbStatementQuantityFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\WbStatementQuantityFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class WbStatementQuantityFeatureTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
	}

	public static function applyProvider() {
		return [
			'single statement with equals' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P999=Q888',
						'field' => 'statement_quantity',
						'eq' => 777,
					],
				],
				'search string' => 'wbstatementquantity:P999=Q888=777',
			],
			'single statement with >' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P999=Q888',
						'field' => 'statement_quantity',
						'gt' => 777,
					],
				],
				'search string' => 'wbstatementquantity:P999=Q888>777',
			],
			'single statement with >=' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P999=Q888',
						'field' => 'statement_quantity',
						'gte' => 777,
					],
				],
				'search string' => 'wbstatementquantity:P999=Q888>=777',
			],
			'single statement with <' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P111=Q222',
						'field' => 'statement_quantity',
						'lt' => 333,
					],
				],
				'search string' => 'wbstatementquantity:P111=Q222<333',
			],
			'single statement with <=' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P111=Q222',
						'field' => 'statement_quantity',
						'lte' => 333,
					],
				],
				'search string' => 'wbstatementquantity:P111=Q222<=333',
			],
			'multiple statements' => [
				'expected' => [
					'bool' => [
						'minimum_should_match' => 1,
						'should' => [
							[ 'term_freq' => [
								'term' => 'P111=Q222',
								'field' => 'statement_quantity',
								'lte' => 333,
							] ],
							[ 'term_freq' => [
								'term' => 'P999=Q888',
								'field' => 'statement_quantity',
								'gt' => 1,
							] ],
						]
					]
				],
				'search string' => 'wbstatementquantity:P111=Q222<=333|P999=Q888>1',
			],
			'some data invalid' => [
				'expected' => [
					'term_freq' => [
						'term' => 'P999=Q888',
						'field' => 'statement_quantity',
						'gt' => 1,
					],
				],
				'search string' => 'wbstatementquantity:INVALID|P999=Q888>1',
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( $expected, $term ) {
		$feature = new WbStatementQuantityFeature();
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertFilter( $feature, $term, $expected, [] );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
	}

	public static function applyNoDataProvider() {
		return [
			'empty data' => [
				'wbstatementquantity:',
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
		$feature = new WbStatementQuantityFeature();
		$this->getKWAssertions()->assertNotConsumed( $feature, $term );
	}

	public function testInvalidStatementWarning() {
		$feature = new WbStatementQuantityFeature();
		$expectedWarnings = [ [ 'wikibasecirrus-wbstatementquantity-feature-no-valid-statements', 'wbstatementquantity' ] ];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue(
			$feature,
			'wbstatementquantity:INVALID',
			[ 'statements' => [], 'operators' => [], 'numbers' => [] ],
			$expectedWarnings
		);
		$kwAssertions->assertExpandedData( $feature, 'wbstatementquantity:INVALID', [], [] );
		$kwAssertions->assertFilter( $feature, 'wbstatementquantity:INVALID', null, $expectedWarnings );
		$kwAssertions->assertNoResultsPossible( $feature, 'wbstatementquantity:INVALID' );
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParseValue( $value, $expected, $warningExpected ) {
		$feature = new WbStatementQuantityFeature();
		$expectedWarnings = $warningExpected ? [
			[ 'wikibasecirrus-wbstatementquantity-feature-no-valid-statements', 'wbstatementquantity' ]
		] : [];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue( $feature, "wbstatementquantity:\"$value\"", $expected, $expectedWarnings );
	}

	public static function parseProvider() {
		return [
			'empty value' => [
				'value' => '',
				'expected' => [
					'statements' => [],
					'operators' => [],
					'numbers' => [],
				],
				'warningExpected' => true,
			],
			'invalid property id' => [
				'value' => 'xyz=test>1',
				'expected' => [
					'statements' => [],
					'operators' => [],
					'numbers' => [],
				],
				'warningExpected' => true,
			],
			'invalid operator' => [
				'value' => 'P999=test|1',
				'expected' => [
					'statements' => [],
					'operators' => [],
					'numbers' => [],
				],
				'warningExpected' => true,
			],
			'invalid value' => [
				'value' => 'P999=Q888>A',
				'expected' => [
					'statements' => [],
					'operators' => [],
					'numbers' => [],
				],
				'warningExpected' => true,
			],
			'single value equals' => [
				'value' => 'P999=Q888=1',
				'expected' => [
					'statements' => [ 'P999=Q888' ],
					'operators' => [ '=' ],
					'numbers' => [ '1' ],
				],
				'warningExpected' => false,
			],
			'single value greater than' => [
				'value' => 'P999=Q888>1',
				'expected' => [
					'statements' => [ 'P999=Q888' ],
					'operators' => [ '>' ],
					'numbers' => [ '1' ],
				],
				'warningExpected' => false,
			],
			'single value greater than or equals' => [
				'value' => 'P999=Q888>=9',
				'expected' => [
					'statements' => [ 'P999=Q888' ],
					'operators' => [ '>=' ],
					'numbers' => [ '9' ],
				],
				'warningExpected' => false,
			],
			'single value less than' => [
				'value' => 'P333=ABCD<9',
				'expected' => [
					'statements' => [ 'P333=ABCD' ],
					'operators' => [ '<' ],
					'numbers' => [ '9' ],
				],
				'warningExpected' => false,
			],
			'single value less than or equals' => [
				'value' => 'P111=ABCD<=9',
				'expected' => [
					'statements' => [ 'P111=ABCD' ],
					'operators' => [ '<=' ],
					'numbers' => [ '9' ],
				],
				'warningExpected' => false,
			],
			'multiple values' => [
				'value' => 'P999=ABCD<9|P777=Q111>1',
				'expected' => [
					'statements' => [ 'P999=ABCD', 'P777=Q111' ],
					'operators' => [ '<', '>' ],
					'numbers' => [ '9', '1' ]
				],
				'warningExpected' => false,
			],
			'multiple values, not all valid' => [
				'value' => 'P999=ABCD=5|p=WXYZ>12345',
				'expected' => [
					'statements' => [ 'P999=ABCD' ],
					'operators' => [ '=' ],
					'numbers' => [ '5' ]
				],
				'warningExpected' => false,
			],
		];
	}

	/**
	 * @return KeywordFeatureAssertions
	 */
	private function getKWAssertions() {
		return new KeywordFeatureAssertions( $this );
	}

}
