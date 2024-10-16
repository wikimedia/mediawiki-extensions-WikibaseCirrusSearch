<?php

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * WikibaseIndexField for any entity being a LabelsProvider.
 * This interface is a companion to {@link LabelsProviderFieldDefinitions} that returns a list
 * of WikibaseIndexField from {@link LabelsProviderFieldDefinitions::getFields()}.
 * It might make sense in the future to move this interface to Wikibase if
 * LabelsProviderFieldDefinitions gets generalized enough that it is usable from extensions willing
 * to populate the labels fields without depending explicitly on WikibaseCirrusSearch.
 *
 * @see LabelsProviderFieldDefinitions
 */
interface WikibaseLabelsIndexField extends WikibaseIndexField {

	/**
	 * Get the indexed values from a {@link LabelsProvider}.
	 * Some implementations might also inspect if the $entity param provides aliases via
	 * {@link AliasesProvider} to index them as well.
	 *
	 * @param LabelsProvider $entity
	 *
	 * @return mixed Get the value of the field to be indexed when this LabelsProvider
	 *               is indexed. This might be an array with nested data, if the field
	 *               is defined with nested type or an int or string for simple field types.
	 */
	public function getLabelsIndexedData( LabelsProvider $entity );
}
