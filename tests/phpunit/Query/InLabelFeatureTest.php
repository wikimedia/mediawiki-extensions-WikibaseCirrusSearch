<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Search\Elastic\Query\InLabelFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\InLabelFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class InLabelFeatureTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		if ( !class_exists( \CirrusSearch::class ) ) {
			$this->markTestSkipped( 'CirrusSearch needed.' );
		}
	}

	public function applyProvider() {
		return [
			'simplest usage' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift',
			],
			'has @ but no language specified' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@',
			],
			'language specified as *' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@*',
			],
			'* is only accepted on its own' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels.en' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@*,en',
				'warnings' => [
					[ 'wikibasecirrussearch-inlabel-unknown-language-code', '*' ],
				],
			],
			'quoted values make a phrase query' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift wrap',
						'fields' => [ 'labels.en' ],
						'operator' => 'and',
						'type' => 'phrase',
					],
				],
				'search string' => 'inlabel:"gift wrap@en"',
			],
			'multiple language phrase query' => [
				'expected' => [
					'multi_match' => [
						'query' => 'manifesto futurista',
						'fields' => [ 'labels.pt-br', 'labels.pt' ],
						'operator' => 'and',
						'type' => 'phrase',
					],
				],
				'search string' => 'inlabel:"manifesto futurista@pt-br,pt"',
			],
			'language is case insensitive' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels.zh' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@ZH',
			],
			'can specify multiple languages' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br', 'labels.pt' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br,pt',
			],
			'apply language fallbacks with *' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br', 'labels.pt', 'labels.en' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br*',
			],
			'unknown languages generate warnings' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels.en' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@en,unk',
				'warnings' => [
					[ 'wikibasecirrussearch-inlabel-unknown-language-code', 'unk' ],
				],
			],
			'only the last @ is consumed' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa@pt-br',
						'fields' => [ 'labels.pt' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br@pt',
			],
			'deduplicates' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt,pt',
			],
			'deduplicates language chains as well' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br', 'labels.pt', 'labels.en' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br*,pt*',
			],
			'language chain without en' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br', 'labels.pt' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br+',
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( array $expected = null, $term, array $expectedWarnings = [], $languageChains = null ) {
		$feature = $this->featureWithMocks( $languageChains );
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertFilter( $feature, $term, $expected, $expectedWarnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public function noResultsProvider() {
		return [
			'all invalid languages prevents results' => [
				'term' => 'inlabel:foo@unk',
				'warnings' => [
					[ 'wikibasecirrussearch-inlabel-unknown-language-code', 'unk' ],
				]
			],
			'only language specified' => [
				'term' => 'inlabel:@en',
				'warnings' => [
					[ 'wikibasecirrussearch-inlabel-no-query-provided' ],
				],
			],
			'value contains only @' => [
				'term' => 'inlabel:@',
				'warnings' => [
					[ 'wikibasecirrussearch-inlabel-no-query-provided' ],
				],
			],
		];
	}

	/**
	 * @dataProvider noResultsProvider
	 */
	public function testNoResults( $term, $expectedWarnings = [] ) {
		$this->getKWAssertions()->assertNoResultsPossible(
			$this->featureWithMocks(), $term, $expectedWarnings );
	}

	public function testLimitFieldCount() {
		$feature = $this->featureWithMocks( [
			'aa' => $this->letterRange( 'aa', 'az' ),
			'bb' => $this->letterRange( 'bb', 'bz' ),
		] );
		$term = 'inlabel:himom@aa*,bb*';
		$expected = [
			'multi_match' => [
				'query' => 'himom',
				'fields' => array_merge(
					$this->letterRange( 'labels.aa', 'labels.az' ),
					$this->letterRange( 'labels.bb', 'labels.be' ) ),
				'operator' => 'and',
			],
		];
		$expectedWarnings = [
			[ 'wikibasecirrussearch-inlabel-too-many-language-codes', InLabelFeature::MAX_FIELDS, 26 * 2 - 1 ],
		];
		$this->getKWAssertions()->assertFilter( $feature, $term, $expected, $expectedWarnings );
	}

	private function featureWithMocks( $languageChains = null ) {
		if ( $languageChains === null ) {
			$languageChains = [
				'pt-br' => [ 'pt-br', 'pt', 'en' ],
				'pt' => [ 'pt', 'en' ],
				'zh' => [
					'zh', 'zh-hans', 'zh-hant', 'zh-cn', 'zh-tw',
					'zh-hk', 'zh-sg', 'zh-mo', 'zh-my', 'en'
				],
			];
		}

		$validLanguages = [];
		foreach ( $languageChains as $lang => $langFallbacks ) {
			$validLanguages[] = $lang;
			foreach ( $langFallbacks as $fallbackLang ) {
				$validLanguages[] = $fallbackLang;
			}
		}

		$factory = $this->getMockBuilder( LanguageFallbackChainFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$factory->expects( $this->any() )
			->method( 'newFromLanguageCode' )
			->will( $this->returnCallback( function ( $langCode ) use ( $languageChains ) {
				$langFallbacks = $languageChains[$langCode] ?? [ 'en' ];
				$fallbackLanguageChain = $this->getMockBuilder( LanguageFallbackChain::class )
					->disableOriginalConstructor()
					->getMock();
				$fallbackLanguageChain->expects( $this->any() )
					->method( 'getFetchLanguageCodes' )
					->will( $this->returnValue( $langFallbacks ) );
				return $fallbackLanguageChain;
			} ) );
		return new InLabelFeature( $factory, $validLanguages );
	}

	public function applyNoDataProvider() {
		return [
			'empty data' => [
				'inlabel:',
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
		$feature = $this->featureWithMocks();
		$this->getKWAssertions()->assertNotConsumed( $feature, $term );
	}

	/**
	 * @return KeywordFeatureAssertions
	 */
	private function getKWAssertions() {
		return new KeywordFeatureAssertions( $this );
	}

	/**
	 * Helper to generate a bunch of language "codes".
	 * @param string $a
	 * @param string $b
	 */
	private function letterRange( $a, $b ) {
		if ( $a > $b ) {
			list( $a, $b ) = [ $b, $a ];
		}
		$range = [];
		while ( $a <= $b ) {
			$range[] = $a;
			$a++;
		}
		return $range;
	}

}
