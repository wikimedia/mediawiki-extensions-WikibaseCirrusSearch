<?php

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/**
 * WikibaseIndexField for any entity being a DescriptionsProvider.
 * This interface is a companion to {@link DescriptionsProviderFieldDefinitions} that returns a list
 * of WikibaseIndexField from {@link DescriptionsProviderFieldDefinitions::getFields()}.
 * It might make sense in the future to move this interface to Wikibase if
 * DescriptionsProviderFieldDefinitions gets generalized enough that it is usable from extensions willing
 * to populate the descriptions fields without depending explicitly on WikibaseCirrusSearch.
 *
 * @see DescriptionsProviderFieldDefinitions
 */
interface WikibaseDescriptionsIndexField extends WikibaseIndexField {

	/**
	 * Get the indexed values from a {@link DescriptionsProvider}.
	 *
	 * @param DescriptionsProvider $entity
	 *
	 * @return mixed Get the value of the field to be indexed when this DescriptionsProvider
	 *               is indexed. This might be an array with nested data, if the field
	 *               is defined with nested type or an int or string for simple field types.
	 */
	public function getDescriptionsIndexedData( DescriptionsProvider $entity );
}
