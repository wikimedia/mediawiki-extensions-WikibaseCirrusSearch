<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Hooks;

use MediaWiki\MediaWikiServices;
use Wikibase\Search\Elastic\WikibaseSearchConfig;

/**
 * Hooks for Wikibase search.
 */
class CirrusSearchConfiguration {

	/**
	 * We need to access the `WikibaseCirrusSearch` configuration from early-initialization
	 * hook contexts where it would not be possible to inject the ConfigFactory service.
	 *
	 * Fortunately, static access to the ConfigFactory is allowed under the
	 * {@link \MediaWiki\Hook\MediaWikiServicesHook::onMediaWikiServices() MediaWikiServicesHook rules}.
	 *
	 * @return WikibaseSearchConfig
	 * @suppress PhanTypeMismatchReturnSuperType
	 */
	public static function getWBCSConfig(): WikibaseSearchConfig {
		return MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'WikibaseCirrusSearch' );
	}

	public static function isWBCSEnabled(): bool {
		return self::getWBCSConfig()->enabled() ?? false;
	}

}
