<?php

/**
 * Search configs for entity types for use with Wikibase.
 */

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Lookup\InProcessCachingDataTypeLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\ItemFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\PropertyFieldDefinitions;
use Wikibase\Search\Elastic\Fields\StatementProviderFieldDefinitions;
use Wikibase\SettingsArray;

return [
	'item' => [
		'entity-search-callback' => function ( WebRequest $request ) {
			$repo = WikibaseRepo::getDefaultInstance();
			return new EntitySearchElastic(
				$repo->getLanguageFallbackChainFactory(),
				$repo->getEntityIdParser(),
				$repo->getUserLanguage(),
				$repo->getContentModelMappings(),
				$request
			);
		},
		'search-field-definitions' => function ( array $languageCodes, SettingsArray $searchSettings ) {
			$repo = WikibaseRepo::getDefaultInstance();
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
			return new ItemFieldDefinitions( [
				new LabelsProviderFieldDefinitions( $languageCodes ),
				new DescriptionsProviderFieldDefinitions( $languageCodes, $config->get( 'UseStemming' ) ),
				StatementProviderFieldDefinitions::newFromSettings(
					new InProcessCachingDataTypeLookup( $repo->getPropertyDataTypeLookup() ),
					$repo->getDataTypeDefinitions()->getSearchIndexDataFormatterCallbacks(),
					$searchSettings
				)
			] );
		},
		'fulltext-search-context' => \Wikibase\Search\Elastic\EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT,
	],
	'property' => [
		'search-field-definitions' => function ( array $languageCodes, SettingsArray $searchSettings ) {
			$repo = WikibaseRepo::getDefaultInstance();
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikibaseCirrusSearch' );
			return new PropertyFieldDefinitions( [
				new LabelsProviderFieldDefinitions( $languageCodes ),
				new DescriptionsProviderFieldDefinitions( $languageCodes, $config->get( 'UseStemming' ) ),
				StatementProviderFieldDefinitions::newFromSettings(
					new InProcessCachingDataTypeLookup( $repo->getPropertyDataTypeLookup() ),
					$repo->getDataTypeDefinitions()->getSearchIndexDataFormatterCallbacks(),
					$searchSettings
				)
			] );
		},
		'entity-search-callback' => function ( WebRequest $request ) {
			$repo = WikibaseRepo::getDefaultInstance();
			return new EntitySearchElastic(
				$repo->getLanguageFallbackChainFactory(),
				$repo->getEntityIdParser(),
				$repo->getUserLanguage(),
				$repo->getContentModelMappings(),
				$request
			);
		},
		'fulltext-search-context' => \Wikibase\Search\Elastic\EntitySearchElastic::CONTEXT_WIKIBASE_FULLTEXT,
	]
];
