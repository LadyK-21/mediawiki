<?php
/**
 * Page history
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Actions
 */

namespace MediaWiki\Actions;

use MediaWiki\Cache\HTMLFileCache;
use MediaWiki\Feed\ChannelFeed;
use MediaWiki\Feed\FeedItem;
use MediaWiki\Feed\FeedUtils;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\HistoryPager;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Utils\MWTimestamp;
use stdClass;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * This class handles printing the history page for an article. In order to
 * be efficient, it uses timestamps rather than offsets for paging, to avoid
 * costly LIMIT,offset queries.
 *
 * Construct it by passing in an Article, and call $h->history() to print the
 * history.
 *
 * @ingroup Actions
 */
class HistoryAction extends FormlessAction {
	private const DIR_PREV = 0;
	private const DIR_NEXT = 1;

	/** @var array Array of message keys and strings */
	public $message;

	/** @inheritDoc */
	public function getName() {
		return 'history';
	}

	/** @inheritDoc */
	public function requiresWrite() {
		return false;
	}

	/** @inheritDoc */
	public function requiresUnblock() {
		return false;
	}

	/** @inheritDoc */
	protected function getPageTitle() {
		return $this->msg( 'history-title' )->plaintextParams( $this->getTitle()->getPrefixedText() );
	}

	/** @inheritDoc */
	protected function getDescription() {
		// Creation of a subtitle link pointing to [[Special:Log]]
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$subtitle = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Log' ),
			$this->msg( 'viewpagelogs' )->text(),
			[],
			[ 'page' => $this->getTitle()->getPrefixedText() ]
		);

		$links = [];
		// Allow extensions to add more links
		$this->getHookRunner()->onHistoryPageToolLinks( $this->getContext(), $linkRenderer, $links );
		if ( $links ) {
			$subtitle .= ''
				. $this->msg( 'word-separator' )->escaped()
				. $this->msg( 'parentheses' )
					->rawParams( $this->getLanguage()->pipeList( $links ) )
					->escaped();
		}
		return Html::rawElement( 'div', [ 'class' => 'mw-history-subtitle' ], $subtitle );
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	private function preCacheMessages() {
		// Precache various messages
		// @phan-suppress-next-line MediaWikiNoIssetIfDefined False positives when documented as nullable
		if ( !isset( $this->message ) ) {
			$this->message = [];
			$msgs = [
				'cur', 'tooltip-cur', 'last', 'tooltip-last', 'pipe-separator',
				'changeslist-nocomment', 'updatedmarker',
			];
			foreach ( $msgs as $msg ) {
				$this->message[$msg] = $this->msg( $msg )->escaped();
			}
		}
	}

	/**
	 * @param WebRequest $request
	 * @return string
	 */
	private function getTimestampFromRequest( WebRequest $request ) {
		// Backwards compatibility checks for URIs with only year and/or month.
		$year = $request->getInt( 'year' );
		$month = $request->getInt( 'month' );
		$day = null;
		if ( $year !== 0 || $month !== 0 ) {
			if ( $year === 0 ) {
				$year = MWTimestamp::getLocalInstance()->format( 'Y' );
			}
			if ( $month < 1 || $month > 12 ) {
				// month is invalid so treat as December (all months)
				$month = 12;
			}
			// month is valid so check day
			$day = cal_days_in_month( CAL_GREGORIAN, $month, $year );

			// Left pad the months and days
			$month = str_pad( (string)$month, 2, "0", STR_PAD_LEFT );
			$day = str_pad( (string)$day, 2, "0", STR_PAD_LEFT );
		}

		$before = $request->getVal( 'date-range-to' );
		if ( $before ) {
			$parts = explode( '-', $before );
			$year = $parts[0];
			// check date input is valid
			if ( count( $parts ) === 3 ) {
				$month = $parts[1];
				$day = $parts[2];
			}
		}
		return $year && $month && $day ? $year . '-' . $month . '-' . $day : '';
	}

	/**
	 * Print the history page for an article.
	 * @return string|null
	 */
	public function onView() {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$config = $this->context->getConfig();
		$services = MediaWikiServices::getInstance();

		// Allow client-side HTTP caching of the history page.
		// But, always ignore this cache if the (logged-in) user has this page on their watchlist
		// and has one or more unseen revisions. Otherwise, we might be showing stale update markers.
		// The Last-Modified for the history page does not change when user's markers are cleared,
		// so going from "some unseen" to "all seen" would not clear the cache.
		// But, when all of the revisions are marked as seen, then only way for new unseen revision
		// markers to appear, is for the page to be edited, which updates page_touched/Last-Modified.
		$watchlistManager = $services->getWatchlistManager();
		$hasUnseenRevisionMarkers = $config->get( MainConfigNames::ShowUpdatedMarker ) &&
			$watchlistManager->getTitleNotificationTimestamp(
				$this->getUser(),
				$this->getTitle()
			);
		if (
			!$hasUnseenRevisionMarkers &&
			$out->checkLastModified( $this->getWikiPage()->getTouched() )
		) {
			return null; // Client cache fresh and headers sent, nothing more to do.
		}

		$this->preCacheMessages();

		# Fill in the file cache if not set already
		if ( HTMLFileCache::useFileCache( $this->getContext() ) ) {
			$cache = new HTMLFileCache( $this->getTitle(), 'history' );
			if ( !$cache->isCacheGood( /* Assume up to date */ ) ) {
				ob_start( [ $cache, 'saveToFileCache' ] );
			}
		}

		// Setup page variables.
		$out->setFeedAppendQuery( 'action=history' );
		$out->addModules( 'mediawiki.action.history' );
		$out->addModuleStyles( [
			'mediawiki.interface.helpers.styles',
			'mediawiki.action.history.styles',
			'mediawiki.special.changeslist',
		] );

		// Handle atom/RSS feeds.
		$feedType = $request->getRawVal( 'feed' );
		if ( $feedType !== null ) {
			$this->feed( $feedType );
			return null;
		}

		$this->addHelpLink(
			'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:History',
			true
		);

		$dbr = $services->getConnectionProvider()->getReplicaDatabase();
		// Fail nicely if article doesn't exist.
		if ( !$this->getWikiPage()->exists() ) {
			$send404Code = $config->get( MainConfigNames::Send404Code );
			if ( $send404Code ) {
				$out->setStatusCode( 404 );
			}
			$out->addWikiMsg( 'nohistory' );

			# show deletion/move log if there is an entry
			LogEventsList::showLogExtract(
				$out,
				[ 'delete', 'move', 'protect', 'merge' ],
				$this->getTitle(),
				'',
				[ 'lim' => 10,
					'conds' => [ $dbr->expr( 'log_action', '!=', 'revision' ) ],
					'showIfEmpty' => false,
					'msgKey' => [ 'moveddeleted-notice' ]
				]
			);

			return null;
		}

		$ts = $this->getTimestampFromRequest( $request );
		$tagFilter = $request->getVal( 'tagfilter' );

		/**
		 * Option to show only revisions that have been (partially) hidden via RevisionDelete
		 */
		if ( $request->getBool( 'deleted' ) ) {
			$conds = [ $dbr->expr( 'rev_deleted', '!=', 0 ) ];
		} else {
			$conds = [];
		}

		// Add the general form.
		$fields = [
			'action' => [
				'name' => 'action',
				'type' => 'hidden',
				'default' => 'history',
			],
			'date-range-to' => [
				'type' => 'date',
				'default' => $ts,
				'label' => $this->msg( 'date-range-to' )->text(),
				'name' => 'date-range-to',
			],
			'tagfilter' => [
				'label-message' => 'tag-filter',
				'type' => 'tagfilter',
				'id' => 'tagfilter',
				'name' => 'tagfilter',
				'value' => $tagFilter,
			],
			'tagInvert' => [
				'type' => 'check',
				'name' => 'tagInvert',
				'label-message' => 'invert',
				'hide-if' => [ '===', 'tagfilter', '' ],
			],
		];
		if ( $this->getAuthority()->isAllowed( 'deletedhistory' ) ) {
			$fields['deleted'] = [
				'type' => 'check',
				'label' => $this->msg( 'history-show-deleted' )->text(),
				'default' => $request->getBool( 'deleted' ),
				'name' => 'deleted',
			];
		}

		$out->enableOOUI();
		$htmlForm = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			->setAction( wfScript() )
			->setCollapsibleOptions( true )
			->setId( 'mw-history-searchform' )
			->setSubmitTextMsg( 'historyaction-submit' )
			->setWrapperAttributes( [ 'id' => 'mw-history-search' ] )
			->setWrapperLegendMsg( 'history-fieldset-title' )
			->prepareForm();

		$out->addHTML( $htmlForm->getHTML( false ) );

		$this->getHookRunner()->onPageHistoryBeforeList(
			$this->getArticle(),
			$this->getContext()
		);

		// Create and output the list.
		$dateComponents = explode( '-', $ts );
		if ( count( $dateComponents ) > 1 ) {
			$y = (int)$dateComponents[0];
			$m = (int)$dateComponents[1];
			$d = (int)$dateComponents[2];
		} else {
			$y = 0;
			$m = 0;
			$d = 0;
		}
		$pager = new HistoryPager(
			$this,
			$y,
			$m,
			$d,
			$tagFilter,
			$request->getCheck( 'tagInvert' ),
			$conds,
			$services->getLinkBatchFactory(),
			$watchlistManager,
			$services->getCommentFormatter(),
			$services->getHookContainer(),
			$services->getChangeTagsStore()
		);
		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
		$out->getMetadata()->setPreventClickjacking( $pager->getPreventClickjacking() );

		return null;
	}

	/**
	 * Fetch an array of revisions, specified by a given limit, offset and
	 * direction. This is now only used by the feeds. It was previously
	 * used by the main UI but that's now handled by the pager.
	 *
	 * @param int $limit The limit number of revisions to get
	 * @param int $offset
	 * @param int $direction Either self::DIR_PREV or self::DIR_NEXT
	 * @return IResultWrapper
	 */
	private function fetchRevisions( $limit, $offset, $direction ) {
		// Fail if article doesn't exist.
		if ( !$this->getTitle()->exists() ) {
			return new FakeResultWrapper( [] );
		}

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		if ( $direction === self::DIR_PREV ) {
			[ $dirs, $oper ] = [ "ASC", ">=" ];
		} else { /* $direction === self::DIR_NEXT */
			[ $dirs, $oper ] = [ "DESC", "<=" ];
		}

		$queryBuilder = MediaWikiServices::getInstance()->getRevisionStore()->newSelectQueryBuilder( $dbr )
			->joinComment()
			->where( [ 'rev_page' => $this->getWikiPage()->getId() ] )
			->useIndex( [ 'revision' => 'rev_page_timestamp' ] )
			->orderBy( [ 'rev_timestamp' ], $dirs )
			->limit( $limit );
		if ( $offset ) {
			$queryBuilder->andWhere( $dbr->expr( 'rev_timestamp', $oper, $dbr->timestamp( $offset ) ) );
		}

		return $queryBuilder->caller( __METHOD__ )->fetchResultSet();
	}

	/**
	 * Output a subscription feed listing recent edits to this page.
	 *
	 * @param string $type Feed type
	 */
	private function feed( $type ) {
		if ( !FeedUtils::checkFeedOutput( $type, $this->getOutput() ) ) {
			return;
		}
		$request = $this->getRequest();

		$feedClasses = $this->context->getConfig()->get( MainConfigNames::FeedClasses );
		'@phan-var array<string,class-string<ChannelFeed>> $feedClasses';
		/** @var ChannelFeed $feed */
		$feed = new $feedClasses[$type](
			$this->getTitle()->getPrefixedText() . ' - ' .
			$this->msg( 'history-feed-title' )->inContentLanguage()->text(),
			$this->msg( 'history-feed-description' )->inContentLanguage()->text(),
			$this->getTitle()->getFullURL( 'action=history' )
		);

		// Get a limit on number of feed entries. Provide a sensible default
		// of 10 if none is defined (but limit to $wgFeedLimit max)
		$limit = $request->getInt( 'limit', 10 );
		$limit = min(
			max( $limit, 1 ),
			$this->context->getConfig()->get( MainConfigNames::FeedLimit )
		);

		$items = $this->fetchRevisions( $limit, 0, self::DIR_NEXT );

		// Preload comments
		$formattedComments = MediaWikiServices::getInstance()->getRowCommentFormatter()
			->formatRows( $items, 'rev_comment' );

		// Generate feed elements enclosed between header and footer.
		$feed->outHeader();
		if ( $items->numRows() ) {
			foreach ( $items as $i => $row ) {
				$feed->outItem( $this->feedItem( $row, $formattedComments[$i] ) );
			}
		} else {
			$feed->outItem( $this->feedEmpty() );
		}
		$feed->outFooter();
	}

	private function feedEmpty(): FeedItem {
		return new FeedItem(
			$this->msg( 'nohistory' )->inContentLanguage()->text(),
			$this->msg( 'history-feed-empty' )->inContentLanguage()->parseAsBlock(),
			$this->getTitle()->getFullURL(),
			wfTimestamp( TS_MW ),
			'',
			$this->getTitle()->getTalkPage()->getFullURL()
		);
	}

	/**
	 * Generate a MediaWiki\Feed\FeedItem object from a given revision table row
	 * Borrows Recent Changes' feed generation functions for formatting;
	 * includes a diff to the previous revision (if any).
	 *
	 * @param stdClass|array $row Database row
	 * @param string $formattedComment The comment in HTML format
	 * @return FeedItem
	 */
	private function feedItem( $row, $formattedComment ) {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$rev = $revisionStore->newRevisionFromRow( $row, 0, $this->getTitle() );
		$prevRev = $revisionStore->getPreviousRevision( $rev );
		$revComment = $rev->getComment() === null ? null : $rev->getComment()->text;
		$text = FeedUtils::formatDiffRow2(
			$this->getTitle(),
			$prevRev ? $prevRev->getId() : false,
			$rev->getId(),
			$rev->getTimestamp(),
			$formattedComment
		);
		$revUserText = $rev->getUser() ? $rev->getUser()->getName() : '';
		if ( $revComment == '' ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$title = $this->msg( 'history-feed-item-nocomment',
				$revUserText,
				$contLang->timeanddate( $rev->getTimestamp() ),
				$contLang->date( $rev->getTimestamp() ),
				$contLang->time( $rev->getTimestamp() )
			)->inContentLanguage()->text();
		} else {
			$title = $revUserText .
				$this->msg( 'colon-separator' )->inContentLanguage()->text() .
				FeedItem::stripComment( $revComment );
		}

		return new FeedItem(
			$title,
			$text,
			$this->getTitle()->getFullURL( 'diff=' . $rev->getId() . '&oldid=prev' ),
			$rev->getTimestamp(),
			$revUserText,
			$this->getTitle()->getTalkPage()->getFullURL()
		);
	}
}

/** @deprecated class alias since 1.44 */
class_alias( HistoryAction::class, 'HistoryAction' );
