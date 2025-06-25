<?php

namespace Wikibase\Search\Elastic;

use MediaWiki\Language\Language;
use MediaWiki\Request\WebRequest;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup;
use Wikibase\Repo\Api\CombinedEntitySearchHelper;
use Wikibase\Repo\Api\EntityIdSearchHelper;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 */
class EntitySearchHelperFactory {
	private EntityIdParser $entityIdParser;
	private LanguageFallbackChainFactory $languageFallbackChainFactory;
	private EntityLookup $entityLookup;
	private TermLookup $termLookup;
	private array $enabledEntityTypes;
	private array $contentModelMappings;

	public function __construct(
		EntityIdParser $entityIdParser,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		EntityLookup $entityLookup,
		TermLookup $termLookup,
		array $enabledEntityTypes,
		array $contentModelMappings
	) {
		$this->entityIdParser = $entityIdParser;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->entityLookup = $entityLookup;
		$this->termLookup = $termLookup;
		$this->enabledEntityTypes = $enabledEntityTypes;
		$this->contentModelMappings = $contentModelMappings;
	}

	public static function newFromGlobalState(): self {
		return new self(
			WikibaseRepo::getEntityIdParser(),
			WikibaseRepo::getLanguageFallbackChainFactory(),
			WikibaseRepo::getEntityLookup(),
			WikibaseRepo::getTermLookup(),
			WikibaseRepo::getEnabledEntityTypes(),
			WikibaseRepo::getContentModelMappings()
		);
	}

	public function newItemSearchForResultLanguage( WebRequest $request, Language $resultLanguage ): EntitySearchHelper {
		return new CombinedEntitySearchHelper(
			[
				new EntityIdSearchHelper(
					$this->entityLookup,
					$this->entityIdParser,
					new LanguageFallbackLabelDescriptionLookup(
						$this->termLookup,
						$this->languageFallbackChainFactory->newFromLanguage( $resultLanguage )
					),
					$this->enabledEntityTypes
				),
				new EntitySearchElastic(
					$this->languageFallbackChainFactory,
					$this->entityIdParser,
					$resultLanguage,
					$this->contentModelMappings,
					$request
				),
			]
		);
	}
}
