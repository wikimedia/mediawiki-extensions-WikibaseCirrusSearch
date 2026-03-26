<?php

namespace Wikibase\Search\Elastic;

use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseCirrusSearch {

	public static function getEntitySearchHelperFactory( ?ContainerInterface $services = null ): EntitySearchHelperFactory {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseCirrusSearch.EntitySearchHelperFactory' );
	}

}
