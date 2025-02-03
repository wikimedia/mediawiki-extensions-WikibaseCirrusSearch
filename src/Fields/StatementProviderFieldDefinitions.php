<?php

namespace Wikibase\Search\Elastic\Fields;

use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * Fields for an object that has statements.
 *
 * @license GPL-2.0-or-later
 * @author Stas Malyshev
 */
class StatementProviderFieldDefinitions implements FieldDefinitions {

	/**
	 * List of properties to index.
	 * @var string[]
	 */
	private $propertyIds;

	/**
	 * @var callable[]
	 */
	private $searchIndexDataFormatters;
	/**
	 * @var DataTypeFactory
	 */
	private $dataTypeFactory;
	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;
	/**
	 * @var array
	 */
	private $indexedTypes;
	/**
	 * @var array
	 */
	private $excludedIds;
	/**
	 * @var array
	 */
	private $allowedQualifierPropertyIdsForQuantityStatements;

	private ?LoggerInterface $logger;

	/**
	 * @var ?callable
	 */
	private $statementProvider;

	public function __construct(
		DataTypeFactory $dataTypeFactory,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		array $searchIndexDataFormatters,
		array $propertyIds,
		array $indexedTypes,
		array $excludedIds,
		array $allowedQualifierPropertyIdsForQuantityStatements,
		?LoggerInterface $logger = null,
		?callable $statementProvider = null
	) {
		$this->propertyIds = $propertyIds;
		$this->searchIndexDataFormatters = $searchIndexDataFormatters;
		$this->dataTypeFactory = $dataTypeFactory;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->indexedTypes = $indexedTypes;
		$this->excludedIds = $excludedIds;
		$this->allowedQualifierPropertyIdsForQuantityStatements =
			$allowedQualifierPropertyIdsForQuantityStatements;
		$this->logger = $logger;
		$this->statementProvider = $statementProvider;
	}

	/**
	 * Get the list of definitions
	 * @return WikibaseIndexField[] key is field name, value is WikibaseIndexField
	 */
	public function getFields() {
		$fields = [
			StatementsField::NAME => new StatementsField(
				$this->dataTypeFactory,
				$this->propertyDataTypeLookup,
				$this->propertyIds,
				$this->indexedTypes,
				$this->excludedIds,
				$this->searchIndexDataFormatters,
				$this->logger,
				$this->statementProvider
			),
			StatementCountField::NAME => new StatementCountField(),
		];
		if ( $this->allowedQualifierPropertyIdsForQuantityStatements ) {
			$fields[StatementQuantityField::NAME] = new StatementQuantityField(
				$this->dataTypeFactory,
				$this->propertyDataTypeLookup,
				$this->propertyIds,
				$this->indexedTypes,
				$this->excludedIds,
				$this->searchIndexDataFormatters,
				$this->allowedQualifierPropertyIdsForQuantityStatements,
				$this->logger
			);
		}
		return $fields;
	}

	/**
	 * Factory to create StatementProviderFieldDefinitions from configs
	 * @param DataTypeFactory$dataTypeFactory
	 * @param PropertyDataTypeLookup $propertyDataTypeLookup
	 * @param callable[] $searchIndexDataFormatters
	 * @param SettingsArray $settings
	 * @param LoggerInterface|null $logger
	 * @param ?callable $statementProvider
	 * @return StatementProviderFieldDefinitions
	 */
	public static function newFromSettings(
		DataTypeFactory $dataTypeFactory,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		array $searchIndexDataFormatters,
		SettingsArray $settings,
		?LoggerInterface $logger = null,
		?callable $statementProvider = null
	): self {
		return new static(
			$dataTypeFactory,
			$propertyDataTypeLookup,
			$searchIndexDataFormatters,
			$settings->getSetting( 'searchIndexProperties' ),
			$settings->getSetting( 'searchIndexTypes' ),
			$settings->getSetting( 'searchIndexPropertiesExclude' ),
			$settings->getSetting( 'searchIndexQualifierPropertiesForQuantity' ),
			$logger,
			$statementProvider
		);
	}

}
