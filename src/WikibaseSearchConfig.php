<?php

namespace Wikibase\Search\Elastic;

use Config;
use Wikibase\Repo\WikibaseRepo;

/**
 * Config class for Wikibase search configs.
 * Provides BC wrapper for old Wikibase search configs.
 */
class WikibaseSearchConfig implements Config {

	const WIKIBASE_SEARCH_CONFIG_PREFIX = 'wgWBCS';

	/**
	 * Global config.
	 * @var \GlobalVarConfig
	 */
	private $globals;

	/**
	 * Wikibase entitySearch config - for BC.
	 * @var array
	 */
	private $wikibaseSettings;

	public function __construct( array $wikibaseSettings ) {
		$this->globals = new \GlobalVarConfig( self::WIKIBASE_SEARCH_CONFIG_PREFIX );
		$this->wikibaseSettings = $wikibaseSettings;
	}

	/**
	 * Create config from globals
	 * @return WikibaseSearchConfig
	 */
	public static function newFromGlobals() {
		$repo = WikibaseRepo::getDefaultInstance();
		$repoSettings = $repo->getSettings();
		return new static( $repoSettings->getSetting( 'entitySearch' ) );
	}

	/**
	 * Get a configuration variable such as "Sitename" or "UploadMaintenance."
	 * TODO: when Wikibase/repo default values are removed, wikibaseSettings
	 * should take precedence, since they are set by the user.
	 * @param string $name Name of configuration option
	 * @param mixed $default Return if value not found.
	 * @return mixed Value configured
	 * @throws \ConfigException
	 */
	public function get( $name, $default = null ) {
		if ( $this->globals->has( $name ) ) {
			$value = $this->globals->get( $name );
			if ( !is_null( $value ) ) {
				return $value;
			}
		}
		$compat_name = lcfirst( $name );
		if ( !array_key_exists( $compat_name, $this->wikibaseSettings ) ) {
			return $default;
		}
		return $this->wikibaseSettings[$compat_name];
	}

	/**
	 * Check whether a configuration option is set for the given name
	 *
	 * @param string $name Name of configuration option
	 * @return bool
	 */
	public function has( $name ) {
		if ( $this->globals->has( $name ) ) {
			return true;
		}
		return array_key_exists( lcfirst( $name ), $this->wikibaseSettings );
	}

	/**
	 * Check whether search functionality for this extension is enabled.
	 */
	public function enabled() {
		// This check is temporary for disabling this extension while
		// Wikibase code is still enabled
		if ( !$this->globals->get( 'UseCirrus' ) ) {
			return null;
		}
		return $this->get( 'UseCirrus' );
	}

}
