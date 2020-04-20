<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use ExtensionRegistry;
use Wikibase\Search\Elastic\Query\HasDataForLangFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\HasDataForLangFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class HasDataForLangFeatureTest extends \MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
	}

	public function applyProvider() {
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
				'search string' => 'haslabel:*',
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
				'search string' => 'hasdescription:en'
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
				'search string' => 'hasdescription:zh,ru'
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
				'search string' => 'hasdescription:*',
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
				'search string' => 'hasdescription:eN',
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
				'search string' => 'hasdescription:de,de'
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
				'search string' => 'haslabel:en'
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
				'search string' => 'haslabel:zh,ru'
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
				'search string' => 'haslabel:eN',
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
				'search string' => 'haslabel:de,de'
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
				'search string' => 'hascaption:en'
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
				'search string' => 'hascaption:zh,ru'
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
				'search string' => 'hascaption:eN',
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
				'search string' => 'hascaption:de,de'
			],
			'too many languages' => [
				'expected' => [
					'bool' => [
						'should' => array_map( function ( $lang ) {
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
				'search string' => 'haslabel:aa,ab,ace,ady,af,ak,als,am,an,ang,ar,arc,arz,as,'
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
	public function testApply( ?array $expected, $term, array $expectedWarnings = [] ) {
		$feature = new HasDataForLangFeature( [ 'aa', 'ab', 'ace', 'ady', 'af', 'ak', 'als', 'am',
												'an', 'ang', 'ar', 'arc', 'arz', 'as', 'ast', 'atj',
												'av', 'ay', 'az', 'azb', 'ba', 'bar', 'bcl', 'be',
												'bg', 'bh', 'de', 'en', 'he', 'ja', 'ru', 'zh' ] );
		$kwAssertions = new KeywordFeatureAssertions( $this );
		$kwAssertions->assertFilter( $feature, $term, $expected, $expectedWarnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public function noResultsProvider() {
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
	public function testNoResults( $term, $expectedWarnings = [] ) {
		( new KeywordFeatureAssertions( $this ) )->assertNoResultsPossible(
			new HasDataForLangFeature( [ 'test' ] ), $term, $expectedWarnings );
	}

	public function applyNoDataProvider() {
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
