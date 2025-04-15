<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Hooks;

use Wikibase\Repo\Hooks\WikibaseRepoEntityTypesHook;

/**
 * Hooks for Wikibase search.
 */
class WikibaseRepoEntityTypesHookHandler extends CirrusSearchConfiguration
	implements WikibaseRepoEntityTypesHook {

	/** @inheritDoc */
	public function onWikibaseRepoEntityTypes( array &$entityTypeDefinitions ): void {
		if ( !CirrusSearchConfiguration::isWBCSEnabled() ) {
			return;
		}
		$entityTypeDefinitions = wfArrayPlus2d(
			require __DIR__ . '/../../WikibaseSearch.entitytypes.php',
			$entityTypeDefinitions
		);
	}

}
