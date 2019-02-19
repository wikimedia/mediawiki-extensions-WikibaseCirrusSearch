<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Profile\ArrayProfileRepository;
use CirrusSearch\Profile\SearchProfileRepositoryTransformer;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\FullTextQueryBuilder;
use CirrusSearch\Search\SearchContext;
use RequestContext;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Fields\StatementsField;
use Wikibase\Search\Elastic\Query\HasWbStatementFeature;
use Wikibase\Search\Elastic\Query\WbStatementQuantityFeature;

/**
 * Hooks for Wikibase search.
 */
class Hooks {

	/**
	 * Setup hook.
	 * Enables/disables CirrusSearch depending on request settings.
	 */
	public static function onSetupAfterCache() {
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$request = RequestContext::getMain()->getRequest();
		$settings = $wikibaseRepo->getSettings();
		$searchSettings = $settings->getSetting( 'entitySearch' );
		$useCirrus = $request->getVal( 'useCirrus' );
		if ( $useCirrus !== null ) {
			// if we have request one, use it
			$searchSettings['useCirrus'] = wfStringToBool( $useCirrus );
			$settings->setSetting( 'entitySearch', $searchSettings );
		}
		if ( $searchSettings['useCirrus'] ) {
			global $wgCirrusSearchExtraIndexSettings;
			// Bump max fields so that labels/descriptions fields fit in.
			$wgCirrusSearchExtraIndexSettings['index.mapping.total_fields.limit'] = 5000;
		}
	}

	/**
	 * Adds the definitions relevant for Search to entity types definitions.
	 *
	 * @see WikibaseSearch.entitytypes.php
	 *
	 * @param array[] $entityTypeDefinitions
	 */
	public static function onWikibaseRepoEntityTypes( array &$entityTypeDefinitions ) {
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		$entityTypeDefinitions = wfArrayPlus2d(
			require __DIR__ . '/../WikibaseSearch.entitytypes.php',
			$entityTypeDefinitions
		);
	}

	/**
	 * Add Wikibase-specific ElasticSearch analyzer configurations.
	 * @param array &$config
	 * @param AnalysisConfigBuilder $builder
	 */
	public static function onCirrusSearchAnalysisConfig( &$config, AnalysisConfigBuilder $builder ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return;
		}
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		static $inHook;
		if ( $inHook ) {
			// Do not call this hook repeatedly, since ConfigBuilder calls AnalysisConfigBuilder
			// FIXME: this is not a very nice hack, but we need it because we want AnalysisConfigBuilder
			// to call the hook, since other extensions may make relevant changes to config.
			// We just don't want to run this specific hook again, but Mediawiki API does not have
			// the means to exclude one hook temporarily.
			return;
		}

		// Analyzer for splitting statements and extracting properties:
		// P31=Q1234 => P31
		$config['analyzer']['extract_wb_property'] = [
			'type' => 'custom',
			'tokenizer' => 'split_wb_statements',
			'filter' => [ 'first_token' ],
		];
		$config['tokenizer']['split_wb_statements'] = [
			'type' => 'pattern',
			'pattern' => StatementsField::STATEMENT_SEPARATOR,
		];
		$config['filter']['first_token'] = [
			'type' => 'limit',
			'max_token_count' => 1
		];

		// Analyzer for extracting quantity data and storing it in a term frequency field
		$config['analyzer']['extract_wb_quantity'] = [
			'type' => 'custom',
			'tokenizer' => 'keyword',
			'filter' => [ 'term_freq' ],
		];

		// Language analyzers for descriptions
		$repo = WikibaseRepo::getDefaultInstance();
		$wbBuilder = new ConfigBuilder( $repo->getTermsLanguages()->getLanguages(),
			$repo->getSettings()->getSetting( 'entitySearch' ),
			$builder
		);
		$inHook = true;
		try {
			$wbBuilder->buildConfig( $config );
		} finally {
			$inHook = false;
		}
	}

	/**
	 * Register our cirrus profiles using WikibaseRepo::getDefaultInstance().
	 *
	 * @param SearchProfileService $service
	 */
	public static function onCirrusSearchProfileService( SearchProfileService $service ) {
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		$settings = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'entitySearch' );
		self::registerSearchProfiles( $service, $settings );
	}

	/**
	 * Register cirrus profiles .
	 * @param SearchProfileService $service
	 * @param array $entitySearchConfig
	 */
	public static function registerSearchProfiles( SearchProfileService $service, array $entitySearchConfig ) {
		$stmtBoost = $entitySearchConfig['statementBoost'] ?? [];
		// register base profiles available on all wikibase installs
		$service->registerFileRepository( SearchProfileService::RESCORE,
			'wikibase_base', __DIR__ . '/config/ElasticSearchRescoreProfiles.php' );
		$service->registerRepository( new SearchProfileRepositoryTransformer(
			ArrayProfileRepository::fromFile(
				SearchProfileService::RESCORE_FUNCTION_CHAINS,
				'wikibase_base',
				__DIR__ . '/config/ElasticSearchRescoreFunctions.php' ),
			[ EntitySearchElastic::STMT_BOOST_PROFILE_REPL => $stmtBoost ]
		) );
		$service->registerFileRepository( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			'wikibase_base', __DIR__ . '/config/EntityPrefixSearchProfiles.php' );
		$service->registerFileRepository( SearchProfileService::FT_QUERY_BUILDER,
			'wikibase_base', __DIR__ . '/config/EntitySearchProfiles.php' );

		// register custom profiles provided in the wikibase config
		if ( isset( $entitySearchConfig['rescoreProfiles'] ) ) {
			$service->registerArrayRepository( SearchProfileService::RESCORE,
				'wikibase_config', $entitySearchConfig['rescoreProfiles'] );
		}
		if ( isset( $entitySearchConfig['rescoreFunctionChains'] ) ) {
			$service->registerRepository( new SearchProfileRepositoryTransformer(
				ArrayProfileRepository::fromArray(
					SearchProfileService::RESCORE_FUNCTION_CHAINS,
					'wikibase_config',
					$entitySearchConfig['rescoreFunctionChains'] ),
				[ EntitySearchElastic::STMT_BOOST_PROFILE_REPL => $stmtBoost ]
			) );
		}
		if ( isset( $entitySearchConfig['prefixSearchProfiles'] ) ) {
			$service->registerArrayRepository( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
				'wikibase_config', $entitySearchConfig['prefixSearchProfiles'] );
		}
		if ( isset( $entitySearchConfig['fulltextSearchProfiles'] ) ) {
			$service->registerArrayRepository( SearchProfileService::FT_QUERY_BUILDER,
				'wikibase_config', $entitySearchConfig['fulltextSearchProfiles'] );
		}

		// Determine the default rescore profile to use for entity autocomplete search
		$defaultRescore = EntitySearchElastic::DEFAULT_RESCORE_PROFILE;
		if ( isset( $entitySearchConfig['defaultPrefixRescoreProfile'] ) ) {
			// If set in config use it
			$defaultRescore = $entitySearchConfig['defaultPrefixRescoreProfile'];
		}
		$service->registerDefaultProfile( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultRescore );
		// add the possibility to override the profile by setting the URI param cirrusRescoreProfile
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusRescoreProfile' );

		// Determine the default query builder profile to use for entity autocomplete search
		$defaultQB = EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE;
		if ( isset( $entitySearchConfig['prefixSearchProfile'] ) ) {
			$defaultQB = $entitySearchConfig['prefixSearchProfile'];
		}
		$service->registerDefaultProfile( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultQB );
		$service->registerUriParamOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusWBProfile' );

		// Determine query builder profile for fulltext search
		$defaultFQB = EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE;
		if ( isset( $entitySearchConfig['fulltextSearchProfile'] ) ) {
			$defaultFQB = $entitySearchConfig['fulltextSearchProfile'];
		}
		$service->registerDefaultProfile( SearchProfileService::FT_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, $defaultFQB );
		$service->registerUriParamOverride( SearchProfileService::FT_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, 'cirrusWBProfile' );

		// Determine the default rescore profile to use for fulltext search
		$defaultFTRescore = EntitySearchElastic::DEFAULT_RESCORE_PROFILE;
		if ( isset( $entitySearchConfig['defaultFulltextRescoreProfile'] ) ) {
			// If set in config use it
			$defaultFTRescore = $entitySearchConfig['defaultFulltextRescoreProfile'];
		}
		$service->registerDefaultProfile( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, $defaultFTRescore );
		// add the possibility to override the profile by setting the URI param cirrusRescoreProfile
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, 'cirrusRescoreProfile' );
	}

	/**
	 * @param FullTextQueryBuilder $builder
	 * @param string $term
	 * @param SearchContext $context
	 */
	public static function onCirrusSearchFulltextQueryBuilderComplete(
		FullTextQueryBuilder $builder,
		$term,
		SearchContext $context
	) {
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		if ( !$context->getConfig()->isLocalWiki() ) {
			// don't mess with interwiki searches
			return;
		}

		$repo = WikibaseRepo::getDefaultInstance();
		$settings = $repo->getSettings()->getSetting( 'entitySearch' );
		if ( !$settings || empty( $settings['useCirrus'] ) ) {
			// Right now our specialized search is Cirrus, so no point in
			// calling dispatcher if Cirrus is disabled.
			return;
		}

		$wbBuilder = new DispatchingQueryBuilder( $repo->getFulltextSearchTypes(),
			$repo->getEntityNamespaceLookup() );
		$wbBuilder->build( $context, $term );
	}

	/**
	 * Add extra cirrus search query features for wikibase
	 *
	 * @param $config (not used, required by hook)
	 * @param array $extraFeatures
	 */
	public static function onCirrusSearchAddQueryFeatures( $config, array &$extraFeatures ) {
		if ( empty( $GLOBALS['wgWikibaseCirrusSearchEnable'] ) ) {
			return;
		}
		$foreignRepoNames = [];
		$foreignRepos = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting(
			'foreignRepositories'
		);
		if ( !empty( $foreignRepos ) ) {
			$foreignRepoNames = array_keys( $foreignRepos );
		}
		$extraFeatures[] = new HasWbStatementFeature( $foreignRepoNames );
		$extraFeatures[] = new WbStatementQuantityFeature( $foreignRepoNames );
	}

}
