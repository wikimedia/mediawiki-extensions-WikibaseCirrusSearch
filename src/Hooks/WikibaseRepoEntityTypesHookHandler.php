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
		$searchDefinitions = require __DIR__ . '/../../WikibaseSearch.entitytypes.php';
		foreach ( $searchDefinitions as $type => $definition ) {
			$entityTypeDefinitions[$type] = $definition + ( $entityTypeDefinitions[$type] ?? [] );
		}
	}

}
