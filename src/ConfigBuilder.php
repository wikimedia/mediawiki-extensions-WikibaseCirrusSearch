<?php
namespace Wikibase\Search\Elastic;

use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use MediaWiki\Config\Config;

/**
 * Utility class to build analyzer configs for ElasticSearch
 */
class ConfigBuilder {

	/**
	 * @param string[] $languageList
	 * @param Config $searchSettings
	 * @param AnalysisConfigBuilder $builder
	 */
	public function __construct(
		private readonly array $languageList,
		private readonly Config $searchSettings,
		private readonly AnalysisConfigBuilder $builder,
	) {
	}

	/**
	 * Build a new all-language analyzer configuration.
	 * This adds analyzers, filters, etc. which are required for language-specific
	 * indexing of Wikidata fields.
	 * @param array[] &$config Existing config which will be modified with new analyzers
	 */
	public function buildConfig( array &$config ) {
		$stemmingSettings = $this->searchSettings->get( 'UseStemming' );

		$stemmedLanguages = array_filter( $this->languageList,
			static function ( $lang ) use ( $stemmingSettings ) {
				return !empty( $stemmingSettings[$lang]['index'] );
			}
		);
		$nonStemmedLanguages = array_diff( $this->languageList, $stemmedLanguages );
		$this->builder->buildLanguageConfigs( $config, $stemmedLanguages,
			[ 'plain', 'plain_search', 'text', 'text_search' ] );
		$this->builder->buildLanguageConfigs( $config, $nonStemmedLanguages,
			[ 'plain', 'plain_search' ] );
	}

}
