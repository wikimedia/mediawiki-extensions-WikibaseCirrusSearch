<?php

use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\EntitySearchHelperFactory;

/** @phpcs-require-sorted-array */
return [
	'WikibaseCirrusSearch.EntitySearchHelperFactory' => static function ( MediaWikiServices $services ): EntitySearchHelperFactory {
		return new EntitySearchHelperFactory(
			WikibaseRepo::getEntityIdParser( $services ),
			WikibaseRepo::getLanguageFallbackChainFactory( $services ),
			WikibaseRepo::getEntityLookup( $services ),
			WikibaseRepo::getTermLookup( $services ),
			WikibaseRepo::getEnabledEntityTypes( $services ),
			WikibaseRepo::getContentModelMappings( $services )
		);
	},
];
