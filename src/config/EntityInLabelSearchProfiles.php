<?php

/**
 * Profile defining weights for query matching options.
 * any - match in any language
 * lang-exact - exact match in specific language
 * lang-folded - casefolded/asciifolded match in specific language
 * lang-tokenized - match in specific language
 * fallback-exact - exact match in fallback language
 * fallback-folded - casefolded/asciifolded match in fallback language
 * fallback-tokenized - match in fallback language
 * fallback-discount - multiplier for each following fallback
 */
return [
	// FIXME: manually tuned, next step is to put in place a golden corpus of
	// graded queries and provide metrics to evaluate the quality objectively.
	'default' => [
		'any' => 0.001,
		'lang-exact' => 2,
		'lang-folded' => 1.6,
		'lang-tokenized' => 1.1,
		'fallback-exact' => 1.9,
		'fallback-folded' => 1.3,
		'fallback-tokenized' => 0.4,
		'fallback-discount' => 0.9,
	]
];
