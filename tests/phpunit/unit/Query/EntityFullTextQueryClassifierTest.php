<?php

namespace Wikibase\Search\Elastic\Tests\Query;

use CirrusSearch\CirrusTestCaseTrait;
use Wikibase\Search\Elastic\Query\EntityFullTextQueryClassifier;

/**
 * @covers \Wikibase\Search\Elastic\Query\EntityFullTextQueryClassifier
 * @group CirrusSearch
 */
class EntityFullTextQueryClassifierTest extends \MediaWikiUnitTestCase {
	use CirrusTestCaseTrait;

	public static function provideQueries() {
		yield 'simple word' => [ 'foo', [ EntityFullTextQueryClassifier::ENTITY_FULL_TEXT ] ];
		yield 'simple bag of words' => [ 'foo bar', [ EntityFullTextQueryClassifier::ENTITY_FULL_TEXT ] ];
		yield 'empty' => [ '', [] ];
		yield 'phrase' => [ '"hello world"', [] ];
		yield 'wildcard' => [ 'hop*d', [] ];
		yield 'prefix' => [ 'hop*', [] ];
		yield 'fuzzy' => [ 'hop~', [] ];
		yield 'phrase prefix' => [ '"foo bar*"', [] ];
		yield 'negation' => [ 'hello -world', [] ];
		yield 'boolean AND' => [ 'foo AND bar', [] ];
		yield 'boolean &&' => [ 'foo && bar', [] ];
		yield 'boolean OR' => [ 'foo OR bar', [] ];
		yield 'boolean ||' => [ 'foo || bar', [] ];
		yield 'negation explicit' => [ 'hello AND NOT world', [] ];
		yield 'keyword intitle' => [ 'intitle:foo', [] ];
	}

	/**
	 * @dataProvider provideQueries
	 */
	public function test( $query, $classes ) {
		$parser = $this->createNewFullTextQueryParser( $this->newHashSearchConfig( [] ) );
		$parsedQuery = $parser->parse( $query );
		$classifier = new EntityFullTextQueryClassifier();
		sort( $classes );
		$actualClasses = $classifier->classify( $parsedQuery );
		sort( $actualClasses );
		$this->assertEquals( $classes, $actualClasses );
	}

	public function testClasses() {
		$classifier = new EntityFullTextQueryClassifier();
		$this->assertEquals(
			[ EntityFullTextQueryClassifier::ENTITY_FULL_TEXT ],
			$classifier->classes()
		);
	}

}
