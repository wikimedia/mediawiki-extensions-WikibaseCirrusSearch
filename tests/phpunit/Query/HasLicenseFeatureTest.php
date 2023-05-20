<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use Wikibase\Search\Elastic\Query\HasLicenseFeature;
use Wikibase\Search\Elastic\Query\HasWbStatementFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\HasLicenseFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class HasLicenseFeatureTest extends MediaWikiIntegrationTestCase {

	private $licenceMapping = [
		'cc-by-sa' => [
			'P1=Q1',
			'P2=Q2',
		],
		'cc-by' => [
			'P11=Q11',
			'P22=Q22',
		],
		'unrestricted' => [
			'P111=Q111',
			'P222=Q222',
			'P333=Q333',
			'P444=Q444',
		]
	];

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
	}

	public static function applyProvider() {
		return [
			'invalid' => [
				'expected' => null,
				'search string' => 'haslicense:invalid',
			],
			'cc-by-sa' => [
				'expected' => [ 'bool' => [
					'minimum_should_match' => 1,
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P1=Q1',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P2=Q2',
							],
						] ],
					]
				] ],
				'search string' => 'haslicense:cc-by-sa',
			],
			'cc-by' => [
				'expected' => [ 'bool' => [
					'minimum_should_match' => 1,
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P11=Q11',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P22=Q22',
							],
						] ],
					]
				] ],
				'search string' => 'haslicense:cc-by',
			],
			'cc-by-sa OR cc-by' => [
				'expected' => [ 'bool' => [
					'minimum_should_match' => 1,
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P1=Q1',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P2=Q2',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P11=Q11',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P22=Q22',
							],
						] ],
					]
				] ],
				'search string' => 'haslicense:cc-by-sa|cc-by',
			],
			'unrestricted' => [
				'expected' => [ 'bool' => [
					'minimum_should_match' => 1,
					'should' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P111=Q111',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P222=Q222',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P333=Q333',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P444=Q444',
							],
						] ],
					]
				] ],
				'search string' => 'haslicense:unrestricted',
			],
			'other' => [
				'expected' => [ 'bool' => [
					'minimum_should_match' => 1,
					'should' => [
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P1',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P2',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P11',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P22',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P111',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P222',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P333',
							],
						] ],
						[ 'match' => [
							'statement_keywords.property' => [
								'query' => 'P444',
							],
						] ]
					],
					'must_not' => [
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P1=Q1',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P2=Q2',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P11=Q11',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P22=Q22',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P111=Q111',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P222=Q222',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P333=Q333',
							],
						] ],
						[ 'match' => [
							'statement_keywords' => [
								'query' => 'P444=Q444',
							],
						] ],
					]
				] ],
				'search string' => 'haslicense:other',
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( ?array $expected, $term ) {
		$feature = new HasLicenseFeature( $this->licenceMapping );
		$kwAssertions = $this->getKWAssertions();
		$expectedWarnings =
			$expected === null ?
				[ [ 'wikibasecirrus-haslicense-feature-no-valid-arguments', 'haslicense' ] ] :
				[];
		$kwAssertions->assertFilter( $feature, $term, $expected, $expectedWarnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public static function applyNoDataProvider() {
		return [
			'empty data' => [
				'haslicense:',
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
		$feature = new HasWbStatementFeature( [ 'P999' ] );
		$this->getKWAssertions()->assertNotConsumed( $feature, $term );
	}

	/**
	 * @return KeywordFeatureAssertions
	 */
	private function getKWAssertions() {
		return new KeywordFeatureAssertions( $this );
	}

}
