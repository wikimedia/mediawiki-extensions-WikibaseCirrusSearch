<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Hooks;

use MediaWiki\Api\Hook\ApiOpenSearchSuggestHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Language\Language;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Search\Elastic\SpecialEntitiesWithoutPageFactory;

class MediaWikiCoreHooksHandler	implements SetupAfterCacheHook, ApiOpenSearchSuggestHook, SpecialPage_initListHook {

	/**
	 * Setup hook.
	 * Enables/disables CirrusSearch depending on request settings.
	 */
	public function onSetupAfterCache() {
		$request = RequestContext::getMain()->getRequest();
		$useCirrus = $request->getVal( 'useCirrus' );
		if ( $useCirrus !== null ) {
			$GLOBALS['wgWBCSUseCirrus'] = wfStringToBool( $useCirrus );
		}
		if ( CirrusSearchConfiguration::isWBCSEnabled() ) {
			global $wgCirrusSearchExtraIndexSettings;
			// Bump max fields so that labels/descriptions fields fit in.
			$wgCirrusSearchExtraIndexSettings['index.mapping.total_fields.limit'] = 5000;
		}
	}

	/**
	 * Will instantiate descriptions for search results.
	 * @param array &$results
	 */
	public function onApiOpenSearchSuggest( &$results ) {
		if ( !CirrusSearchConfiguration::isWBCSEnabled() ) {
			return;
		}

		if ( !$results ) {
			return;
		}

		self::amendSearchResults( RequestContext::getMain()->getLanguage(), $results );
	}

	/**
	 * Register special pages.
	 *
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		$list['EntitiesWithoutLabel'] = [
			SpecialEntitiesWithoutPageFactory::class,
			'newSpecialEntitiesWithoutLabel'
		];

		$list['EntitiesWithoutDescription'] = [
			SpecialEntitiesWithoutPageFactory::class,
			'newSpecialEntitiesWithoutDescription'
		];
	}

	/**
	 * Will instantiate descriptions for search results.
	 *
	 * This is public because we want also to be able to use it from tests, and with the
	 * TestingAccessWrapper it isn't possible to call the function at runtime with a pass-by-reference
	 * parameter.
	 *
	 * @param Language $lang
	 * @param array &$results
	 */
	public static function amendSearchResults( Language $lang, array &$results ) {
		$idParser = WikibaseRepo::getEntityIdParser();
		$entityIds = [];
		$namespaceLookup = WikibaseRepo::getEntityNamespaceLookup();

		foreach ( $results as &$result ) {
			if ( empty( $result['title'] ) ||
				!$namespaceLookup->isEntityNamespace( $result['title']->getNamespace() ) ) {
				continue;
			}
			try {
				$title = $result['title']->getText();
				$entityId = $idParser->parse( $title );
				$entityIds[] = $entityId;
				$result['entityId'] = $entityId;
			} catch ( EntityIdParsingException ) {
				continue;
			}
		}
		if ( !$entityIds ) {
			return;
		}
		$lookup = WikibaseRepo::getFallbackLabelDescriptionLookupFactory()
			->newLabelDescriptionLookup( $lang, $entityIds );
		$formatterFactory = WikibaseRepo::getEntityLinkFormatterFactory();
		foreach ( $results as &$result ) {
			if ( empty( $result['entityId'] ) ) {
				continue;
			}
			$entityId = $result['entityId'];
			unset( $result['entityId'] );
			$label = $lookup->getLabel( $entityId );
			if ( !$label ) {
				continue;
			}
			$linkFormatter = $formatterFactory->getLinkFormatter( $entityId->getEntityType(), $lang );
			$result['extract'] = strip_tags( $linkFormatter->getHtml( $entityId, [
				'value' => $label->getText(),
				'language' => $label->getActualLanguageCode(),
			] ) );
		}
	}

}
