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
	 * @var ?callable
	 */
	private $statementProvider;

	/**
	 * @param DataTypeFactory $dataTypeFactory
	 * @param PropertyDataTypeLookup $propertyDataTypeLookup
	 * @param callable[] $searchIndexDataFormatters
	 * @param string[] $propertyIds List of properties to index
	 * @param array $indexedTypes
	 * @param array $excludedIds
	 * @param array $allowedQualifierPropertyIdsForQuantityStatements
	 * @param LoggerInterface|null $logger
	 * @param callable|null $statementProvider
	 */
	public function __construct(
		private readonly DataTypeFactory $dataTypeFactory,
		private readonly PropertyDataTypeLookup $propertyDataTypeLookup,
		private readonly array $searchIndexDataFormatters,
		private readonly array $propertyIds,
		private readonly array $indexedTypes,
		private readonly array $excludedIds,
		private readonly array $allowedQualifierPropertyIdsForQuantityStatements,
		private readonly ?LoggerInterface $logger = null,
		?callable $statementProvider = null,
	) {
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
	 * @param DataTypeFactory $dataTypeFactory
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
