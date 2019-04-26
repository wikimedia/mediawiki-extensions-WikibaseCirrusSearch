<?php

namespace Wikibase\Search\Elastic;

use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Profile\ArrayProfileRepository;
use CirrusSearch\Profile\SearchProfileRepositoryTransformer;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\FullTextQueryBuilder;
use CirrusSearch\Search\SearchContext;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\Fields\StatementsField;
use Wikibase\Search\Elastic\Query\HasDataForLangFeature;
use Wikibase\Search\Elastic\Query\HasWbStatementFeature;
use Wikibase\Search\Elastic\Query\InLabelFeature;
use Wikibase\Search\Elastic\Query\WbStatementQuantityFeature;
use Wikibase\Lib\WikibaseContentLanguages;

/**
 * Hooks for Wikibase search.
 */
class Hooks {

	/**
	 * Setup hook.
	 * Enables/disables CirrusSearch depending on request settings.
	 */
	public static function onSetupAfterCache() {
		$request = RequestContext::getMain()->getRequest();
		$useCirrus = $request->getVal( 'useCirrus' );
		if ( $useCirrus !== null ) {
			$GLOBALS['wgWBCSUseCirrus'] = wfStringToBool( $useCirrus );
		}
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $config
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( $config->enabled() ) {
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
		$wbcsConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $wbcsConfig
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( !$wbcsConfig->enabled() ) {
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
		$wbcsConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $wbcsConfig
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( !$wbcsConfig->enabled() ) {
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
			MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' ),
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
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $config
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( !defined( 'MW_PHPUNIT_TEST' ) && !$config->enabled() ) {
			return;
		}

		/* @phan-suppress-next-line PhanTypeMismatchArgument */
		self::registerSearchProfiles( $service, $config );
	}

	/**
	 * Register config variable containing search profiles.
	 * @param string $profileName Name of the variable (in config context) that contains profiles
	 * @param string $repoType Cirrus repo type
	 * @param SearchProfileService $service
	 * @param WikibaseSearchConfig $entitySearchConfig Config object
	 */
	private static function registerArrayProfile(
		$profileName,
		$repoType,
		SearchProfileService $service,
		WikibaseSearchConfig $entitySearchConfig
	) {
		$profile = $entitySearchConfig->get( $profileName );
		if ( $profile ) {
			$service->registerArrayRepository( $repoType, 'wikibase_config', $profile );
		}
	}

	/**
	 * Register cirrus profiles .
	 * @param SearchProfileService $service
	 * @param WikibaseSearchConfig $entitySearchConfig
	 */
	public static function registerSearchProfiles( SearchProfileService $service, WikibaseSearchConfig $entitySearchConfig ) {
		$stmtBoost = $entitySearchConfig->get( 'StatementBoost' );
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
		self::registerArrayProfile( 'RescoreProfiles', SearchProfileService::RESCORE,
			$service, $entitySearchConfig );
		// Register function chains
		$chains = $entitySearchConfig->get( 'RescoreFunctionChains' );
		if ( $chains ) {
			$service->registerRepository( new SearchProfileRepositoryTransformer(
				ArrayProfileRepository::fromArray(
					SearchProfileService::RESCORE_FUNCTION_CHAINS,
					'wikibase_config',
					$chains ),
				[ EntitySearchElastic::STMT_BOOST_PROFILE_REPL => $stmtBoost ]
			) );
		}

		self::registerArrayProfile( 'PrefixSearchProfiles',
			EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			$service, $entitySearchConfig );
		self::registerArrayProfile( 'FulltextSearchProfiles',
			SearchProfileService::FT_QUERY_BUILDER,
			$service, $entitySearchConfig );

		// Determine the default rescore profile to use for entity autocomplete search
		$defaultRescore = $entitySearchConfig->get( 'DefaultPrefixRescoreProfile',
			EntitySearchElastic::DEFAULT_RESCORE_PROFILE );
		$service->registerDefaultProfile( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultRescore );
		// add the possibility to override the profile by setting the URI param cirrusRescoreProfile
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusRescoreProfile' );

		// Determine the default query builder profile to use for entity autocomplete search
		$defaultQB = $entitySearchConfig->get( 'PrefixSearchProfile',
			EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE );

		$service->registerDefaultProfile( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultQB );
		$service->registerUriParamOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusWBProfile' );

		// Determine query builder profile for fulltext search
		$defaultFQB = $entitySearchConfig->get( 'FulltextSearchProfile',
			EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE );

		$service->registerDefaultProfile( SearchProfileService::FT_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, $defaultFQB );
		$service->registerUriParamOverride( SearchProfileService::FT_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT, 'cirrusWBProfile' );

		// Determine the default rescore profile to use for fulltext search
		$defaultFTRescore = $entitySearchConfig->get( 'DefaultFulltextRescoreProfile',
			EntitySearchElastic::DEFAULT_RESCORE_PROFILE );

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
		if ( !$context->getConfig()->isLocalWiki() ) {
			// don't mess with interwiki searches
			return;
		}

		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $config
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( !$config->enabled() ) {
			// Right now our specialized search is Cirrus, so no point in
			// calling dispatcher if Cirrus is disabled.
			return;
		}

		if ( !$config->get( 'EnableDispatchingQueryBuilder' ) ) {
			// When the DispatchingQueryBuilder is enabled multi-namespace
			// searches that include an entity namespace are not fully
			// supported, instead overriding the main search with the entity
			// search.
			// Without the dispatching query builder enabled there will be no
			// specialized entity full text search. Instead full text search
			// will operate on whatever content was indexed into the standard
			// CirrusSearch fields.
			return;
		}

		$repo = WikibaseRepo::getDefaultInstance();
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
		$searchConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
		/**
		 * @var WikibaseSearchConfig $searchConfig
		 */
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		if ( !$searchConfig->enabled() ) {
			return;
		}
		$foreignRepoNames = [];
		$repo = WikibaseRepo::getDefaultInstance();
		$foreignRepos = $repo->getSettings()->getSetting(
			'foreignRepositories'
		);
		if ( !empty( $foreignRepos ) ) {
			$foreignRepoNames = array_keys( $foreignRepos );
		}
		$extraFeatures[] = new HasWbStatementFeature( $foreignRepoNames );
		$extraFeatures[] = new WbStatementQuantityFeature( $foreignRepoNames );

		$languageCodes = WikibaseContentLanguages::getDefaultInstance()
			->getContentLanguages( 'term' )->getLanguages();
		$extraFeatures[] = new InLabelFeature( $repo->getLanguageFallbackChainFactory(), $languageCodes );

		$extraFeatures[] = new HasDataForLangFeature( $languageCodes );
	}

}
