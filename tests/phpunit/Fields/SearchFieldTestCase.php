<?php

namespace Wikibase\Search\Elastic\Tests\Fields;

use CirrusSearch\CirrusSearch;
use CirrusSearch\SearchConfig;
use ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use SearchEngine;
use Wikibase\Search\Elastic\Tests\WikibaseSearchTestCase;

/**
 * Helper test class for search field testing.
 */
class SearchFieldTestCase extends MediaWikiIntegrationTestCase {
	use WikibaseSearchTestCase;

	/**
	 * Prepare search engine mock suitable for testing search fields.
	 * @return SearchEngine
	 */
	protected function getSearchEngineMock() {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ) ) {
			$searchEngine = $this->createMock( CirrusSearch::class );
			$searchEngine->method( 'getConfig' )->willReturn( new SearchConfig() );
		} else {
			$searchEngine = $this->createMock( SearchEngine::class );
		}
		return $searchEngine;
	}

}
