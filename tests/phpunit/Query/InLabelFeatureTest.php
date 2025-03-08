<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Query\KeywordFeatureAssertions;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Search\Elastic\Query\InLabelFeature;

/**
 * @covers \Wikibase\Search\Elastic\Query\InLabelFeature
 *
 * @group WikibaseElastic
 * @group Wikibase
 */
class InLabelFeatureTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
	}

	public static function applyProvider() {
		return [
			'simplest usage' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift',
			],
			'incaption alias for WikibaseMediaInfo' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all.plain', 'descriptions.*.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'incaption:gift',
			],
			'has @ but no language specified' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@',
			],
			'language specified as *' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels_all.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@*',
			],
			'* is only accepted on its own' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels.en.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@*,en',
				'warnings' => [
					[ 'wikibasecirrus-keywordfeature-unknown-language-code', 'inlabel', '*' ],
				],
			],
			'quoted values make a phrase query' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift wrap',
						'fields' => [ 'labels.en.plain' ],
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
						'fields' => [ 'labels.pt-br.plain', 'labels.pt.plain' ],
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
						'fields' => [ 'labels.zh.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@ZH',
			],
			'can specify multiple languages' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br.plain', 'labels.pt.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br,pt',
			],
			'apply language fallbacks with *' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br.plain', 'labels.pt.plain', 'labels.en.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br*',
			],
			'apply language fallbacks with * for incaption' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [
							'labels.pt-br.plain',
							'descriptions.pt-br.plain',
							'labels.pt.plain',
							'descriptions.pt.plain',
							'labels.en.plain',
							'descriptions.en.plain',
						],
						'operator' => 'and',
					],
				],
				'search string' => 'incaption:colaborativa@pt-br*',
			],
			'unknown languages generate warnings' => [
				'expected' => [
					'multi_match' => [
						'query' => 'gift',
						'fields' => [ 'labels.en.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:gift@en,unk',
				'warnings' => [
					[ 'wikibasecirrus-keywordfeature-unknown-language-code', 'inlabel', 'unk' ],
				],
			],
			'only the last @ is consumed' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa@pt-br',
						'fields' => [ 'labels.pt.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br@pt',
			],
			'deduplicates' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt,pt',
			],
			'deduplicates language chains as well' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br.plain', 'labels.pt.plain', 'labels.en.plain' ],
						'operator' => 'and',
					],
				],
				'search string' => 'inlabel:colaborativa@pt-br*,pt*',
			],
			'language chain without en' => [
				'expected' => [
					'multi_match' => [
						'query' => 'colaborativa',
						'fields' => [ 'labels.pt-br.plain', 'labels.pt.plain' ],
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
	public function testApply( ?array $expected, $term, array $expectedWarnings = [], $languageChains = null ) {
		$feature = $this->featureWithMocks( $languageChains );
		$kwAssertions = $this->getKWAssertions();
		$kwAssertions->assertFilter( $feature, $term, $expected, $expectedWarnings );
		$kwAssertions->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		if ( $expected === null ) {
			$kwAssertions->assertNoResultsPossible( $feature, $term );
		}
	}

	public static function noResultsProvider() {
		return [
			'all invalid languages prevents results' => [
				'term' => 'inlabel:foo@unk',
				'warnings' => [
					[ 'wikibasecirrus-keywordfeature-unknown-language-code', 'inlabel', 'unk' ],
				]
			],
			'only language specified' => [
				'term' => 'inlabel:@en',
				'warnings' => [
					[ 'wikibasecirrus-inlabel-no-query-provided' ],
				],
			],
			'value contains only @' => [
				'term' => 'inlabel:@',
				'warnings' => [
					[ 'wikibasecirrus-inlabel-no-query-provided' ],
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
					$this->letterRange( 'aa', 'az', 'labels.', '.plain' ),
					$this->letterRange( 'bb', 'be', 'labels.', '.plain' ) ),
				'operator' => 'and',
			],
		];
		$expectedWarnings = [
			[ 'wikibasecirrus-keywordfeature-too-many-language-codes', 'inlabel', InLabelFeature::MAX_FIELDS, 26 * 2 - 1 ],
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

		$factory = $this->createMock( LanguageFallbackChainFactory::class );
		$factory->method( 'newFromLanguageCode' )
			->willReturnCallback( function ( $langCode ) use ( $languageChains ) {
				$langFallbacks = $languageChains[$langCode] ?? [ 'en' ];
				$fallbackLanguageChain = $this->createMock( TermLanguageFallbackChain::class );
				$fallbackLanguageChain->method( 'getFetchLanguageCodes' )
					->willReturn( $langFallbacks );
				return $fallbackLanguageChain;
			} );
		return new InLabelFeature( $factory, $validLanguages );
	}

	public static function applyNoDataProvider() {
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
	 */
	private function letterRange( string $a, string $b, string $prefix = '', string $suffix = '' ): array {
		if ( $a > $b ) {
			[ $a, $b ] = [ $b, $a ];
		}
		$range = [];
		while ( $a <= $b ) {
			$range[] = $prefix . $a . $suffix;
			$a++;
		}
		return $range;
	}

}
