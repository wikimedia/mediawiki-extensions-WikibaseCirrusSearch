<?php

declare( strict_types = 1 );

namespace Wikibase\Search\Elastic\Hooks;

use HtmlArmor;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Search\Hook\ShowSearchHitHook;
use MediaWiki\Search\Hook\ShowSearchHitTitleHook;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Title\Title;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Repo\Hooks\Formatters\EntityLinkFormatterFactory;
use Wikibase\Repo\Hooks\ShowSearchHitHandler;
use Wikibase\Search\Elastic\EntityResult;

/**
 * Handler to format entities in the search results
 *
 * @license GPL-2.0-or-later
 * @author Matěj Suchánek
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class CirrusShowSearchHitHooksHandler implements ShowSearchHitHook, ShowSearchHitTitleHook {

	private EntityLinkFormatterFactory $linkFormatterFactory;
	private EntityIdLookup $entityIdLookup;

	/**
	 * @param EntityIdLookup $entityIdLookup
	 * @param EntityLinkFormatterFactory $linkFormatterFactory
	 */
	public function __construct(
		EntityIdLookup $entityIdLookup,
		EntityLinkFormatterFactory $linkFormatterFactory
	) {
		$this->entityIdLookup = $entityIdLookup;
		$this->linkFormatterFactory = $linkFormatterFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onShowSearchHit( $searchPage, $result,
		$terms, &$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related,
		&$html
	) {
		if ( $result instanceof EntityResult ) {
			$this->showEntityResultHit( $searchPage, $result, $terms,
				$link, $redirect, $section, $extract, $score, $size, $date, $related, $html );
		}
	}

	/**
	 * Show result hit which is the result of Cirrus-driven entity search.
	 *
	 * @param SpecialSearch $searchPage
	 * @param EntityResult $result
	 * @param string[] $terms
	 * @param string &$link
	 * @param string &$redirect
	 * @param string &$section
	 * @param string &$extract
	 * @param string &$score
	 * @param string &$size
	 * @param string &$date
	 * @param string &$related
	 * @param string &$html
	 */
	private function showEntityResultHit( SpecialSearch $searchPage, EntityResult $result, array $terms,
		&$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html
	) {
		$extract = '';
		$displayLanguage = $searchPage->getLanguage()->getCode();
		// Put highlighted description of the item as the extract
		ShowSearchHitHandler::addDescription( $extract, $result->getDescriptionHighlightedData(), $searchPage );
		// Add extra data
		$extra = $result->getExtraDisplay();
		if ( $extra ) {
			$attr = [ 'class' => 'wb-itemlink-description' ];
			$extra = ShowSearchHitHandler::withLanguage( $extra, $displayLanguage );
			ShowSearchHitHandler::addLanguageAttrs( $attr, $displayLanguage, $extra );
			$section = $searchPage->msg( 'colon-separator' )->escaped();
			$section .= Html::rawElement( 'span', $attr, HtmlArmor::getHtml( $extra['value'] ) );
		}
		// set $size to size metrics
		$size = $searchPage->msg(
			'wikibase-search-result-stats',
			$result->getStatementCount(),
			$result->getSitelinkCount()
		)->escaped();
	}

	/**
	 * @inheritDoc
	 *
	 * @todo Add highlighting when Q##-id matches and not label text.
	 */
	public function onShowSearchHitTitle(
		&$title,
		&$titleSnippet,
		$result,
		$terms,
		$specialSearch,
		&$query,
		&$attributes
	) {
		if ( $result instanceof EntityResult ) {
			$this->getEntityLink( $specialSearch->getContext(), $result, $title, $titleSnippet, $attributes,
				$specialSearch->getLanguage()->getCode() );
		}
	}

	/**
	 * Generate link text for Title link in search hit.
	 * @param IContextSource $context
	 * @param EntityResult $result
	 * @param Title $title
	 * @param string|HtmlArmor &$html Variable where HTML will be placed
	 * @param array &$attributes Link tag attributes, can add more
	 * @param string $displayLanguage
	 */
	private function getEntityLink(
		IContextSource $context,
		EntityResult $result,
		Title $title,
		&$html,
		&$attributes,
		$displayLanguage
	) {
		$entityId = $this->entityIdLookup->getEntityIdForTitle( $title );
		if ( !$entityId ) {
			return;
		}
		$linkFormatter = $this->linkFormatterFactory->getDefaultLinkFormatter( $context->getLanguage() );
		// Highlighter already encodes and marks up the HTML
		$html = new HtmlArmor(
			$linkFormatter->getHtml( $entityId,
				ShowSearchHitHandler::withLanguage( $result->getLabelHighlightedData(), $displayLanguage )
			)
		);

		$attributes['title'] = $linkFormatter->getTitleAttribute(
			$entityId,
			$result->getLabelData(),
			$result->getDescriptionData()
		);
	}

}
