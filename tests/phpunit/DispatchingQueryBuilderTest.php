<?php
namespace Wikibase\Search\Elastic\Tests;

use CirrusSearch;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\FullTextQueryBuilder;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use MediaWikiTestCase;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Search\Elastic\DispatchingQueryBuilder;

/**
 * @covers \Wikibase\Search\Elastic\DispatchingQueryBuilder
 *
 * @group Wikibase
 * @license GPL-2.0-or-later
 * @author  Stas Malyshev
 */
class DispatchingQueryBuilderTest extends MediaWikiTestCase {
	use WikibaseSearchTestCase;

	/**
	 * @var array
	 */
	public static $buildCalled = [];
	/**
	 * @var FullTextQueryBuilder
	 */
	private static $mockBuilder1;
	/**
	 * @var FullTextQueryBuilder
	 */
	private static $mockBuilder2;
	/**
	 * @var array
	 */
	public static $PROFILES;

	public function setUp() {
		parent::setUp();
		if ( !class_exists( CirrusSearch::class ) ) {
			$this->markTestSkipped( 'CirrusSearch not installed, skipping' );
		}
		$this->enableWBCS();

		self::$buildCalled = [];
	}

	// phpcs:disable Squiz.Classes.SelfMemberReference.NotUsed
	public static function setupBeforeClass() {
		self::$mockBuilder1 = new class implements FullTextQueryBuilder {

			public function build( SearchContext $searchContext, $term ) {
				DispatchingQueryBuilderTest::$buildCalled[] = get_class( $this );
			}

			public static function newFromGlobals( $settings ) {
				return new static();
			}

			public function buildDegraded( SearchContext $searchContext ) {
				return false;
			}

		};

		self::$mockBuilder2 = new class implements FullTextQueryBuilder {

			public function build( SearchContext $searchContext, $term ) {
				DispatchingQueryBuilderTest::$buildCalled[] = get_class( $this );
			}

			public static function newFromGlobals( $settings ) {
				return new static();
			}

			public function buildDegraded( SearchContext $searchContext ) {
				return false;
			}

		};

		self::$PROFILES = [
			'profile1' => [
				'builder_class' => get_class( self::$mockBuilder1 ),
				'settings' => []
			],
			'profile2' => [
				'builder_class' => get_class( self::$mockBuilder2 ),
				'settings' => []
			],
			'profile3' => [
				'builder_class' => \stdClass::class,
				'settings' => []
			],
		];

		parent::setUpBeforeClass();
	}

	private static $NS_MAP = [
		0 => 'test-item',
		1 => 'test-property',
	];

	/**
	 * @return SearchConfig
	 */
	private function getMockConfig() {

		$config = $this->getMockBuilder( SearchConfig::class )
			->disableOriginalConstructor()
			->getMock();

		$serviceMock = $this->getMockBuilder( SearchProfileService::class )
			->disableOriginalConstructor()
			->getMock();
		$serviceMock->method( "loadProfile" )->willReturnCallback(
			function ( $type, $value )  {
				$this->assertEquals( SearchProfileService::FT_QUERY_BUILDER, $type );
				return DispatchingQueryBuilderTest::$PROFILES[$value] ?? null;
			}
		);

		$config->method( "getProfileService" )->willReturn( $serviceMock );

		return $config;
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	private function getMockEntityNamespaceLookup() {
		$mockLookup = $this->getMockBuilder( EntityNamespaceLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mockLookup->method( 'isEntityNamespace' )->willReturnCallback( function ( $ns ) {
			return $ns < 10;
		} );

		$mockLookup->method( 'getEntityType' )->willReturnCallback( function ( $ns ) {
			return self::$NS_MAP[$ns] ?? null;
		} );

		return $mockLookup;
	}

	public function provideBuilderData() {
		return [
			"no entity defs" => [
				[],
				[ 0, 1 ],
				[]
			],
			"one entity def" => [
				[
					'test-item' => 'profile1',
				],
				[ 0 ],
				[ 'profile1' ],
			],
			"one entity def 2" => [
				[
					'test-item' => 'profile2',
				],
				[ 0 ],
				[ 'profile2' ],
			],
			"two defs, same handler" => [
				[
					'test-item' => 'profile1',
					'test-property' => 'profile1',
				],
				[ 0, 1 ],
				[ 'profile1' ],
			],
			"bad def" => [
				[
					'test-item' => 'profile3',
				],
				[ 0 ],
				[],
				[
					[ 'wikibasecirrus-search-config-badclass', 'stdClass' ]
				]
			],
			"bad profile" => [
				[
					'test-item' => 'profile4',
				],
				[ 0 ],
				[],
				[
					[ 'wikibasecirrus-search-config-notfound', 'profile4' ]
				]
			],
			"mixed handlers" => [
				[
					'test-item' => 'profile1',
					'test-property' => 'profile2',
				],
				[ 0, 1 ],
				[],
			],
			"mixed with non-entity" => [
				[
					'test-item' => 'profile1',
				],
				[ 0, 11 ],
				[ 'profile1' ],
				[
					[ 'wikibasecirrus-search-namespace-mix' ]
				]
			],
			"two defs with non-entity" => [
				[
					'test-item' => 'profile1',
					'test-property' => 'profile2',
				],
				[ 0, 1, 11 ],
				[],
			],
			"mixed with non-entity 2" => [
				[
					'test-item' => 'profile1',
				],
				[ 0, 1 ],
				[ 'profile1' ],
				[
					[ 'wikibasecirrus-search-namespace-mix' ]
				]
			],
			"null namespaces" => [
				[
					'test-item' => 'profile1',
				],
				null,
				[],
			],

		];
	}

	/**
	 * @dataProvider provideBuilderData
	 * @param array $defs
	 * @param int[] $namespaces
	 * @param string[] $called
	 * @param string[] $warnings
	 */
	public function testDispatchBuilder( $defs, $namespaces, $called, $warnings = [] ) {
		$builder = new DispatchingQueryBuilder( $defs, $this->getMockEntityNamespaceLookup() );

		$context = new SearchContext( $this->getMockConfig() );
		$context->setNamespaces( $namespaces );
		$builder->build( $context, "test" );

		$called_classes = array_map( function ( $prof ) {
			return self::$PROFILES[$prof]['builder_class'] ?? null;
		}, $called );

		$this->assertEquals( $called_classes, self::$buildCalled, "Callers do not match" );
		$this->assertEquals( $warnings, $context->getWarnings(), "Warnings do not match" );
	}

}
