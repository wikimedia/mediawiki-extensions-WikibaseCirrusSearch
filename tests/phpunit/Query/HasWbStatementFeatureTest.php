<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use Elastica\Query\Match;
use Elastica\Query\Prefix;
use ExtensionRegistry;
use Wikibase\Search\Elastic\Fields\StatementsField;
use Wikibase\Search\Elastic\Query\HasWbStatementFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\HasWbStatementFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class HasWbStatementFeatureTest extends \MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
	}

	public function applyProvider() {
		return [
			'single statement entity' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P999=Q888',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999=Q888',
			],
			'single statement string' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P999=12345',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999=12345',
			],
			'multiple statements' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P999=Q888',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P777=someString',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999=Q888|P777=someString',
			],
			'some data invalid' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P999=Q888',
							],
						] ],
					]
				] ],
				'search string' => 'haswbstatement:INVALID|P999=Q888',
			],
			'all data invalid' => [
				'expected' => null,
				'search string' => 'haswbstatement:INVALID',
			],
			'property only' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P999',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999',
			],
			'property and value' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P999',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P777=someString',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999|P777=someString',
			],
			'prefix' => [
				'expected' => [ 'bool' => [
					'should' => [
						[ 'prefix' => [
							'statement_keywords' => [
								'value' => 'P999=Q888[P111=',
								'rewrite' => 'top_terms_1024',
							],
						] ]
					]
				] ],
				'search string' => 'haswbstatement:P999=Q888[P111=*',
			],
			'existence' => [
				'expected' => [
					'exists' => [
						'field' => 'statement_keywords'
					]
				],
				'search_string' => 'haswbstatement:*',
			],
			'existence short circuits the rest of bool query' => [
				'expected' => [
					'exists' => [
						'field' => 'statement_keywords'
					]
				],
				'search_string' => 'haswbstatement:P999=Q888|*',
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( ?array $expected, $term ) {
		$feature = new HasWbStatementFeature();
		$kwAssertions = $this->getKWAssertions();
		$expectedWarnings = $expected === null ? [ [ 'wikibasecirrus-haswbstatement-feature-no-valid-statements', 'haswbstatement' ] ] : [];
		$kwAssertions->assertFilter( $feature, $term, $expected, $expectedWarnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public function applyNoDataProvider() {
		return [
			'empty data' => [
				'haswbstatement:',
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
		$feature = new HasWbStatementFeature();
		$this->getKWAssertions()->assertNotConsumed( $feature, $term );
	}

	public function testInvalidStatementWarning() {
		$feature = new HasWbStatementFeature();
		$expectedWarnings = [ [ 'wikibasecirrus-haswbstatement-feature-no-valid-statements', 'haswbstatement' ] ];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue( $feature, 'haswbstatement:INVALID', [], $expectedWarnings );
		$kwAssertions->assertExpandedData( $feature, 'haswbstatement:INVALID', [], [] );
		$kwAssertions->assertFilter( $feature, 'haswbstatement:INVALID', null, $expectedWarnings );
		$kwAssertions->assertNoResultsPossible( $feature, 'haswbstatement:INVALID' );
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParseValue( $value, $expected, $warningExpected ) {
		$feature = new HasWbStatementFeature();
		$expectedWarnings = $warningExpected ? [ [ 'wikibasecirrus-haswbstatement-feature-no-valid-statements', 'haswbstatement' ] ] : [];
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertParsedValue( $feature, "haswbstatement:\"$value\"", $expected, $expectedWarnings );
	}

	public function parseProvider() {
		return [
			'empty value' => [
				'value' => '',
				'expected' => [],
				'warningExpected' => true,
			],
			'invalid value' => [
				'value' => 'xyz=12345',
				'expected' => [],
				'warningExpected' => true,
			],
			'single value Q-id' => [
				'value' => 'P999=Q888',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P999=Q888'
					]
				],
				'warningExpected' => false,
			],
			'single value other id' => [
				'value' => 'P999=AB123',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P999=AB123'
					]
				],
				'warningExpected' => false,
			],
			'multiple values' => [
				'value' => 'P999=Q888|P777=12345',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P999=Q888'
					],
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P777=12345'
					],
				],
				'warningExpected' => false,
			],
			'multiple values, not all valid' => [
				'value' => 'P999=Q888|p=12345',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P999=Q888'
					],
				],
				'warningExpected' => false,
			],
			'property-only' => [
				'value' => 'P999',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME . '.property',
						'string' => 'P999'
					],
				],
				'warningExpected' => false,
			],
			'invalid property-only' => [
				'value' => 'P123,abc',
				'expected' => [],
				'warningExpected' => true,
			],
			'invalid and valid property-only' => [
				'value' => 'P123,abc|P345',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME . '.property',
						'string' => 'P345'
					],
				],
				'warningExpected' => false,
			],
			'prefix search' => [
				'value' => 'P999=P888[P111*',
				'expected' => [
					[
						'class' => Prefix::class,
						'field' => StatementsField::NAME,
						'string' => 'P999=P888[P111'
					],
				],
				'warningExpected' => false,
			],
			'normal, property-only and prefix search simultaneously' => [
				'value' => 'P111=Q222|P333|P444=Q555[P666*',
				'expected' => [
					[
						'class' => Match::class,
						'field' => StatementsField::NAME,
						'string' => 'P111=Q222'
					],
					[
						'class' => Match::class,
						'field' => StatementsField::NAME . '.property',
						'string' => 'P333'
					],
					[
						'class' => Prefix::class,
						'field' => StatementsField::NAME,
						'string' => 'P444=Q555[P666'
					],
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
