<?php

/**
 * Search configs for entity types for use with Wikibase.
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use Wikibase\DataModel\Services\Lookup\InProcessCachingDataTypeLookup;
use Wikibase\Lib\EntityTypeDefinitions as Def;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\EntitySearchHelperFactory;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\ItemFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\PropertyFieldDefinitions;
use Wikibase\Search\Elastic\Fields\StatementProviderFieldDefinitions;

return [
	'item' => [
		Def::ENTITY_SEARCH_CALLBACK => static function ( WebRequest $request ) {
			$context = new RequestContext();
			$context->setRequest( $request );
			$userLanguage = $context->getLanguage();

			return EntitySearchHelperFactory::newFromGlobalState()
				->newItemPropertySearchHelper( $request, $userLanguage );
		},
		Def::SEARCH_FIELD_DEFINITIONS => static function ( array $languageCodes, SettingsArray $searchSettings ) {
			$services = MediaWikiServices::getInstance();
			$configFactory = $services->getConfigFactory();
			return new ItemFieldDefinitions( [
				new LabelsProviderFieldDefinitions( $languageCodes, $configFactory ),
				new DescriptionsProviderFieldDefinitions( $languageCodes, $configFactory ),
				StatementProviderFieldDefinitions::newFromSettings(
					WikibaseRepo::getDataTypeFactory( $services ),
					new InProcessCachingDataTypeLookup(
						WikibaseRepo::getPropertyDataTypeLookup( $services ) ),
					WikibaseRepo::getDataTypeDefinitions( $services )
						->getSearchIndexDataFormatterCallbacks(),
					$searchSettings,
					WikibaseRepo::getLogger( $services )
				)
			] );
		},
		Def::FULLTEXT_SEARCH_CONTEXT => EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT,
	],
	'property' => [
		Def::SEARCH_FIELD_DEFINITIONS => static function ( array $languageCodes, SettingsArray $searchSettings ) {
			$services = MediaWikiServices::getInstance();
			$configFactory = $services->getConfigFactory();
			return new PropertyFieldDefinitions( [
				new LabelsProviderFieldDefinitions( $languageCodes, $configFactory ),
				new DescriptionsProviderFieldDefinitions( $languageCodes, $configFactory ),
				StatementProviderFieldDefinitions::newFromSettings(
					WikibaseRepo::getDataTypeFactory( $services ),
					new InProcessCachingDataTypeLookup(
						WikibaseRepo::getPropertyDataTypeLookup( $services ) ),
					WikibaseRepo::getDataTypeDefinitions( $services )
						->getSearchIndexDataFormatterCallbacks(),
					$searchSettings,
					WikibaseRepo::getLogger( $services )
				)
			] );
		},
		Def::ENTITY_SEARCH_CALLBACK => static function ( WebRequest $request ) {
			$context = new RequestContext();
			$context->setRequest( $request );
			$userLanguage = $context->getLanguage();

			return new \Wikibase\Repo\Api\PropertyDataTypeSearchHelper(
				EntitySearchHelperFactory::newFromGlobalState()
				->newItemPropertySearchHelper( $request, $userLanguage ),
				WikibaseRepo::getPropertyDataTypeLookup()
			);
		},
		Def::FULLTEXT_SEARCH_CONTEXT => EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT,
	]
];
