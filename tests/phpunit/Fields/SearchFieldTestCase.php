<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch;
use MediaWikiTestCase;
use PHPUnit4And6Compat;
use SearchEngine;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * Helper test class for search field testing.
 */
class SearchFieldTestCase extends MediaWikiTestCase {
	use PHPUnit4And6Compat;
	use WikibaseSearchTestCase;

	/**
	 * Prepare search engine mock suitable for testing search fields.
	 * @return SearchEngine
	 */
	protected function getSearchEngineMock() {
		if ( class_exists( CirrusSearch::class ) ) {
			$searchEngine = $this->getMockBuilder( CirrusSearch::class )->getMock();
			$searchEngine->method( 'getConfig' )->willReturn( new CirrusSearch\SearchConfig() );
		} else {
			$searchEngine = $this->getMockBuilder( SearchEngine::class )->getMock();
		}
		return $searchEngine;
	}

}
