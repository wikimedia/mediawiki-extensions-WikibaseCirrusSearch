<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Hooks;

use CirrusSearch\Hooks\CirrusSearchAddQueryFeaturesHook;
use CirrusSearch\Hooks\CirrusSearchAnalysisConfigHook;
use CirrusSearch\Hooks\CirrusSearchProfileServiceHook;
use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Profile\ArrayProfileRepository;
use CirrusSearch\Profile\SearchProfileRepositoryTransformer;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\SearchConfig;
use MediaWiki\Config\ConfigException;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\ConfigBuilder;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\Fields\StatementsField;
use Wikibase\Search\Elastic\Query\HasDataForLangFeature;
use Wikibase\Search\Elastic\Query\HasLicenseFeature;
use Wikibase\Search\Elastic\Query\HasWbStatementFeature;
use Wikibase\Search\Elastic\Query\InLabelFeature;
use Wikibase\Search\Elastic\Query\WbStatementQuantityFeature;
use Wikibase\Search\Elastic\WikibaseSearchConfig;
use Wikimedia\Assert\Assert;

/**
 * Hooks for Wikibase search.
 */
class CirrusSearchHooksHandler implements
	CirrusSearchAnalysisConfigHook,
	CirrusSearchProfileServiceHook,
	CirrusSearchAddQueryFeaturesHook
{

	private const LANGUAGE_SELECTOR_PREFIX = "language_selector_prefix";

	/**
	 * Add Wikibase-specific ElasticSearch analyzer configurations.
	 * @param array &$config
	 * @param AnalysisConfigBuilder $builder
	 */
	public function onCirrusSearchAnalysisConfig( array &$config, AnalysisConfigBuilder $builder ): void {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return;
		}
		$wbcsConfig = CirrusSearchConfiguration::getWBCSConfig();
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
		$wbBuilder = new ConfigBuilder( WikibaseRepo::getTermsLanguages()->getLanguages(),
			$wbcsConfig,
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
	 * Register our cirrus profiles using WikibaseRepo.
	 *
	 * @param SearchProfileService $service
	 */
	public function onCirrusSearchProfileService( SearchProfileService $service ): void {
		$config = CirrusSearchConfiguration::getWBCSConfig();
		if ( !defined( 'MW_PHPUNIT_TEST' ) && !$config->enabled() ) {
			return;
		}

		$namespacesForContexts = [];
		$entityNsLookup = WikibaseRepo::getEntityNamespaceLookup();
		$localEntityTypes = WikibaseRepo::getLocalEntityTypes();
		foreach ( WikibaseRepo::getFulltextSearchTypes() as $type => $profileContext ) {
			if ( !in_array( $type, $localEntityTypes ) ) {
				// Do not enable profiles for entity types that are not local
				// e.g. when using MediaInfo items and properties are not managed by this wiki
				// and thus should not enable specific profiles for them.
				continue;
			}
			$namespace = $entityNsLookup->getEntityNamespace( $type );
			if ( $namespace === null ) {
				continue;
			}
			$namespacesForContexts[$profileContext][] = $namespace;
		}

		self::registerSearchProfiles( $service, $config, $namespacesForContexts );
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
	 * Register cirrus profiles.
	 * (Visible for testing purposes)
	 * @param SearchProfileService $service
	 * @param WikibaseSearchConfig $entitySearchConfig
	 * @param int[][] $namespacesForContexts list of namespaces indexed by profile context name
	 * @see SearchProfileService
	 * @see WikibaseRepo::getFulltextSearchTypes()
	 * @throws ConfigException
	 */
	public static function registerSearchProfiles(
		SearchProfileService $service,
		WikibaseSearchConfig $entitySearchConfig,
		array $namespacesForContexts
	) {
		$stmtBoost = $entitySearchConfig->get( 'StatementBoost' );
		// register base profiles available on all wikibase installs
		$service->registerFileRepository( SearchProfileService::RESCORE,
			'wikibase_base', __DIR__ . '/../config/ElasticSearchRescoreProfiles.php' );
		$service->registerRepository( new SearchProfileRepositoryTransformer(
			ArrayProfileRepository::fromFile(
				SearchProfileService::RESCORE_FUNCTION_CHAINS,
				'wikibase_base',
				__DIR__ . '/../config/ElasticSearchRescoreFunctions.php' ),
			[ EntitySearchElastic::STMT_BOOST_PROFILE_REPL => $stmtBoost ]
		) );
		$service->registerFileRepository( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			'wikibase_base', __DIR__ . '/../config/EntityPrefixSearchProfiles.php' );
		$service->registerFileRepository( EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			'wikibase_base', __DIR__ . '/../config/EntityInLabelSearchProfiles.php' );
		$service->registerFileRepository( SearchProfileService::FT_QUERY_BUILDER,
			'wikibase_base', __DIR__ . '/../config/EntitySearchProfiles.php' );

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
		self::registerArrayProfile( 'InLabelSearchProfiles',
			EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			$service, $entitySearchConfig );

		// Determine the default rescore profile to use for entity autocomplete search
		$defaultRescore = $entitySearchConfig->get( 'DefaultPrefixRescoreProfile',
			EntitySearchElastic::DEFAULT_RESCORE_PROFILE );
		$service->registerDefaultProfile( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultRescore );
		// Check for a variation of the default profile with the requested language code appended. If available
		// use the language specific profile instead of the default profile.
		$service->registerContextualOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, "{$defaultRescore}-{lang}", [ '{lang}' => 'language' ] );
		// add the possibility to override the profile by setting the URI param cirrusRescoreProfile
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusRescoreProfile' );

		// Determine the default query builder profile to use for entity autocomplete search
		$defaultQB = $entitySearchConfig->get( 'PrefixSearchProfile',
			EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE );

		$service->registerDefaultProfile( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, $defaultQB );
		$service->registerContextualOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, "{$defaultQB}-{lang}", [ '{lang}' => 'language' ] );
		$service->registerUriParamOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX, 'cirrusWBProfile' );

		// Determine the default rescore profile to use for entity search by label
		$defaultInLabelRescore = 'wikibase_in_label';
		$service->registerDefaultProfile( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, $defaultInLabelRescore );
		$service->registerConfigOverride(
			SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL,
			$entitySearchConfig,
			'DefaultInLabelRescoreProfile'
		);
		// Check for a variation of the default profile with the requested language code appended. If available
		// use the language specific profile instead of the default profile.
		$service->registerContextualOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, "{$defaultInLabelRescore}-{lang}", [ '{lang}' => 'language' ] );
		// add the possibility to override the profile by setting the URI param cirrusRescoreProfile
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, 'cirrusRescoreProfile' );

		// Determine the default query builder profile to use for entity search by label
		$defaultInLabelQB = 'default';
		$service->registerConfigOverride(
			EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL,
			$entitySearchConfig,
			'InLabelSearchProfile'
		);
		$service->registerDefaultProfile( EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, $defaultInLabelQB );
		$service->registerContextualOverride( EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, "{$defaultInLabelQB}-{lang}", [ '{lang}' => 'language' ] );
		$service->registerUriParamOverride( EntitySearchElastic::WIKIBASE_IN_LABEL_QUERY_BUILDER,
			EntitySearchElastic::CONTEXT_WIKIBASE_IN_LABEL, 'cirrusWBProfile' );

		// Determine query builder profile for fulltext search
		$defaultFQB = $entitySearchConfig->get( 'FulltextSearchProfile',
			EntitySearchElastic::DEFAULT_FULL_TEXT_QUERY_BUILDER_PROFILE );

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

		// create a new search context for the language selector in the Special:NewLexeme
		$service->registerDefaultProfile( SearchProfileService::RESCORE, self::LANGUAGE_SELECTOR_PREFIX,
			EntitySearchElastic::DEFAULT_RESCORE_PROFILE );
		$service->registerConfigOverride( SearchProfileService::RESCORE, self::LANGUAGE_SELECTOR_PREFIX,
			$entitySearchConfig, 'LanguageSelectorRescoreProfile' );
		$service->registerUriParamOverride( SearchProfileService::RESCORE,
			self::LANGUAGE_SELECTOR_PREFIX, 'cirrusRescoreProfile' );
		$service->registerDefaultProfile( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER, self::LANGUAGE_SELECTOR_PREFIX,
			EntitySearchElastic::DEFAULT_QUERY_BUILDER_PROFILE );
		$service->registerConfigOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER, self::LANGUAGE_SELECTOR_PREFIX,
			$entitySearchConfig, 'LanguageSelectorPrefixSearchProfile' );
		$service->registerUriParamOverride( EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			self::LANGUAGE_SELECTOR_PREFIX, 'cirrusWBProfile' );
		$languageSelectorChains = $entitySearchConfig->get( 'LanguageSelectorRescoreFunctionChains' );

		if ( $languageSelectorChains ) {
			$languageSelectorBoosts = $entitySearchConfig->get( 'LanguageSelectorStatementBoost' );
			$service->registerRepository( new SearchProfileRepositoryTransformer(
				ArrayProfileRepository::fromArray(
					SearchProfileService::RESCORE_FUNCTION_CHAINS,
					'wikibase_config_language_selector',
					$languageSelectorChains ),
				[ EntitySearchElastic::STMT_BOOST_PROFILE_REPL => $languageSelectorBoosts ]
			) );
		}
		// Declare "search routes" for wikibase full text search types
		// Source of the routes is $namespacesForContexts which is a "reversed view"
		// of WikibaseRepo::getFulltextSearchTypes().
		// It maps the namespaces to a profile context (e.g. EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT)
		// and will tell cirrus to use the various components we declare in the SearchProfileService
		// above.
		// In this case since wikibase owns these namespaces we score the routes at 1.0 which discards
		// any other routes and eventually fails if another extension
		// tries to own our namespace.
		// For now we only accept simple bag of words queries but this will change in the future
		// when query builders will manipulate the parsed query.
		foreach ( $namespacesForContexts as $profileContext => $namespaces ) {
			Assert::precondition( is_string( $profileContext ),
				'$namespacesForContexts keys must be strings and refer to the profile context to use' );
			$service->registerFTSearchQueryRoute(
				$profileContext,
				1.0,
				$namespaces,
				// The wikibase builders only supports simple queries for now
				[ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ]
			);
		}
	}

	/**
	 * Add extra cirrus search query features for wikibase
	 *
	 * @param \CirrusSearch\SearchConfig $config (not used, required by hook)
	 * @param array &$extraFeatures
	 */
	public function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ): void {
		$searchConfig = CirrusSearchConfiguration::getWBCSConfig();
		if ( !$searchConfig->enabled() ) {
			return;
		}
		$extraFeatures[] = new HasWbStatementFeature();
		$extraFeatures[] = new WbStatementQuantityFeature();

		$licenseMapping = HasLicenseFeature::getConfiguredLicenseMap( $searchConfig );
		$extraFeatures[] = new HasLicenseFeature( $licenseMapping );

		$languageCodes = WikibaseRepo::getTermsLanguages()->getLanguages();
		$extraFeatures[] = new InLabelFeature( WikibaseRepo::getLanguageFallbackChainFactory(), $languageCodes );

		$extraFeatures[] = new HasDataForLangFeature( $languageCodes );
	}

}
