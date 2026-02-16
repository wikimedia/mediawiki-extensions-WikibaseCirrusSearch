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

	public function __construct(
		private readonly EntityIdParser $entityIdParser,
		private readonly LanguageFallbackChainFactory $languageFallbackChainFactory,
		private readonly EntityLookup $entityLookup,
		private readonly TermLookup $termLookup,
		private readonly array $enabledEntityTypes,
		private readonly array $contentModelMappings,
	) {
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

	public function newItemPropertySearchHelper( WebRequest $request, Language $resultLanguage ): EntitySearchHelper {
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
