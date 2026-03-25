<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use Wikibase\Search\Elastic\Query\HasDataForLangFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\HasDataForLangFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class HasDataForLangFeatureTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
	}

	public static function applyProvider() {
		return [
			'all languages' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'labels_all.plain'
								]
							]
						]
					]
				],
				'term' => 'haslabel:*',
			],
			'(hasdescription) description exists' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.en.plain'
								]
							]
						]
					]
				],
				'term' => 'hasdescription:en'
			],
			'(hasdescription) multiple languages' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.zh.plain',
								],
							],
							[
								'exists' => [
									'field' => 'descriptions.ru.plain',
								],
							]
						]
					]
				],
				'term' => 'hasdescription:zh,ru'
			],
			'(hasdescription) all languages' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.*.plain'
								]
							]
						]
					]
				],
				'term' => 'hasdescription:*',
			],
			'(hasdescription) language is case insensitive' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.en.plain'
								]
							]
						]
					]
				],
				'term' => 'hasdescription:eN',
			],
			'(hasdescription) deduplicates language codes' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.de.plain'
								]
							]
						]
					]
				],
				'term' => 'hasdescription:de,de'
			],
			'(haslabel) label exists' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'labels.en.plain'
								]
							]
						]
					]
				],
				'term' => 'haslabel:en'
			],
			'(haslabel) multiple languages' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'labels.zh.plain',
								],
							],
							[
								'exists' => [
									'field' => 'labels.ru.plain',
								],
							]
						]
					]
				],
				'term' => 'haslabel:zh,ru'
			],
			'(haslabel) language is case insensitive' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'labels.en.plain'
								]
							]
						]
					]
				],
				'term' => 'haslabel:eN',
			],
			'(haslabel) deduplicates language codes' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'labels.de.plain'
								]
							]
						]
					]
				],
				'term' => 'haslabel:de,de'
			],
			'(hascaption) label exists' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.en.plain'
								]
							]
						]
					]
				],
				'term' => 'hascaption:en'
			],
			'(hascaption) multiple languages' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.zh.plain',
								],
							],
							[
								'exists' => [
									'field' => 'descriptions.ru.plain',
								],
							]
						]
					]
				],
				'term' => 'hascaption:zh,ru'
			],
			'(hascaption) language is case insensitive' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.en.plain'
								]
							]
						]
					]
				],
				'term' => 'hascaption:eN',
			],
			'(hascaption) deduplicates language codes' => [
				'expected' => [
					'bool' => [
						'should' => [
							[
								'exists' => [
									'field' => 'descriptions.de.plain'
								]
							]
						]
					]
				],
				'term' => 'hascaption:de,de'
			],
			'too many languages' => [
				'expected' => [
					'bool' => [
						'should' => array_map( static function ( $lang ) {
							return [
								'exists' => [
									'field' => 'labels.' . $lang . '.plain'
								]
							];
						}, [ 'aa', 'ab', 'ace', 'ady', 'af', 'ak', 'als', 'am', 'an', 'ang',
							'ar', 'arc', 'arz', 'as', 'ast', 'atj', 'av', 'ay', 'az', 'azb',
							'ba', 'bar', 'bcl', 'be', 'bg', 'bh', 'de', 'en', 'he', 'ja' ]
						),
					]
				],
				'term' => 'haslabel:aa,ab,ace,ady,af,ak,als,am,an,ang,ar,arc,arz,as,'
								   . 'ast,atj,av,ay,az,azb,ba,bar,bcl,be,bg,bh,de,en,he,ja,ru,zh',
				'warnings' => [
					[ 'wikibasecirrus-keywordfeature-too-many-language-codes', 'haslabel', 30, 32 ]
				]
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( ?array $expected, $term, array $warnings = [] ) {
		$feature = new HasDataForLangFeature( [ 'aa', 'ab', 'ace', 'ady', 'af', 'ak', 'als', 'am',
												'an', 'ang', 'ar', 'arc', 'arz', 'as', 'ast', 'atj',
												'av', 'ay', 'az', 'azb', 'ba', 'bar', 'bcl', 'be',
												'bg', 'bh', 'de', 'en', 'he', 'ja', 'ru', 'zh' ] );
		$kwAssertions = new KeywordFeatureAssertions( $this );
		$kwAssertions->assertFilter( $feature, $term, $expected, $warnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public static function noResultsProvider() {
		return [
			'unknown language' => [
				'term' => 'haslabel:unk',
				'warnings' => [
					[ 'wikibasecirrus-keywordfeature-unknown-language-code', 'haslabel', 'unk' ],
				],
			],
		];
	}

	/**
	 * @dataProvider noResultsProvider
	 */
	public function testNoResults( $term, $warnings = [] ) {
		( new KeywordFeatureAssertions( $this ) )->assertNoResultsPossible(
			new HasDataForLangFeature( [ 'test' ] ), $term, $warnings );
	}

	public static function applyNoDataProvider() {
		return [
			'only keyword provided' => [
				'hasdescription:',
			],
			'no query' => [
				'',
			],
		];
	}

	/**
	 * @dataProvider applyNoDataProvider
	 */
	public function testNotConsumed( $term ) {
		( new KeywordFeatureAssertions( $this ) )
			->assertNotConsumed( new HasDataForLangFeature( [ 'test' ] ), $term );
	}

}
