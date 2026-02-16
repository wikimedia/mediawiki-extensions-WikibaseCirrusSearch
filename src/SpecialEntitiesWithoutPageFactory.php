<?php

namespace Wikibase\Search\Elastic;

use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\EntityFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\TermIndexEntry;
use Wikibase\Repo\WikibaseRepo;

/**
 * Factory to create special pages.
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialEntitiesWithoutPageFactory {

	private static function newFromGlobalState(): self {
		return new self(
			WikibaseRepo::getLocalEntityTypes(),
			WikibaseRepo::getTermsLanguages(),
			WikibaseRepo::getLanguageNameLookupFactory()->getForAutonyms(),
			WikibaseRepo::getEntityFactory(),
			WikibaseRepo::getEntityNamespaceLookup()
		);
	}

	public static function newSpecialEntitiesWithoutLabel(): SpecialEntitiesWithoutPage {
		return self::newFromGlobalState()->createSpecialEntitiesWithoutLabel();
	}

	public static function newSpecialEntitiesWithoutDescription(): SpecialEntitiesWithoutPage {
		return self::newFromGlobalState()->createSpecialEntitiesWithoutDescription();
	}

	/**
	 * @param string[] $entityTypes
	 * @param ContentLanguages $termsLanguages
	 * @param LanguageNameLookup $languageNameLookup
	 * @param EntityFactory $entityFactory
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 */
	public function __construct(
		private readonly array $entityTypes,
		private readonly ContentLanguages $termsLanguages,
		private readonly LanguageNameLookup $languageNameLookup,
		private readonly EntityFactory $entityFactory,
		private readonly EntityNamespaceLookup $entityNamespaceLookup,
	) {
	}

	public function createSpecialEntitiesWithoutLabel(): SpecialEntitiesWithoutPage {
		$supportedEntityTypes = [];
		foreach ( $this->entityTypes as $entityType ) {
			if ( $this->entityFactory->newEmpty( $entityType ) instanceof LabelsProvider ) {
				$supportedEntityTypes[] = $entityType;
			}
		}
		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutLabel',
			TermIndexEntry::TYPE_LABEL,
			'wikibasecirrus-entitieswithoutlabel-legend',
			$supportedEntityTypes,
			$this->termsLanguages,
			$this->languageNameLookup,
			$this->entityNamespaceLookup
		);
	}

	public function createSpecialEntitiesWithoutDescription(): SpecialEntitiesWithoutPage {
		$supportedEntityTypes = [];
		foreach ( $this->entityTypes as $entityType ) {
			if ( $this->entityFactory->newEmpty( $entityType ) instanceof DescriptionsProvider ) {
				$supportedEntityTypes[] = $entityType;
			}
		}
		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutDescription',
			TermIndexEntry::TYPE_DESCRIPTION,
			'wikibasecirrus-entitieswithoutdescription-legend',
			$supportedEntityTypes,
			$this->termsLanguages,
			$this->languageNameLookup,
			$this->entityNamespaceLookup
		);
	}

}
