const HighlightColors = require( '../HighlightColors.js' );

/**
 * List of changes.
 *
 * @class mw.rcfilters.ui.ChangesListWrapperWidget
 * @ignore
 * @extends OO.ui.Widget
 *
 * @param {mw.rcfilters.dm.FiltersViewModel} filtersViewModel View model
 * @param {mw.rcfilters.dm.ChangesListViewModel} changesListViewModel View model
 * @param {mw.rcfilters.Controller} controller
 * @param {jQuery} $changesListRoot Root element of the changes list to attach to
 * @param {Object} [config] Configuration object
 */
const ChangesListWrapperWidget = function MwRcfiltersUiChangesListWrapperWidget(
	filtersViewModel,
	changesListViewModel,
	controller,
	$changesListRoot,
	config
) {
	config = Object.assign( {}, config, {
		$element: $changesListRoot
	} );

	// Parent
	ChangesListWrapperWidget.super.call( this, config );

	this.filtersViewModel = filtersViewModel;
	this.changesListViewModel = changesListViewModel;
	this.controller = controller;

	// Events
	this.filtersViewModel.connect( this, {
		itemUpdate: 'onItemUpdate',
		highlightChange: 'onHighlightChange'
	} );
	this.changesListViewModel.connect( this, {
		invalidate: 'onModelInvalidate',
		update: 'onModelUpdate'
	} );

	this.$element
		.addClass( 'mw-rcfilters-ui-changesListWrapperWidget' )
		// We handle our own display/hide of the empty results message
		// We keep the timeout class here and remove it later, since at this
		// stage it is still needed to identify that the timeout occurred.
		.removeClass( 'mw-changeslist-empty' );
};

/* Initialization */

OO.inheritClass( ChangesListWrapperWidget, OO.ui.Widget );

/**
 * Respond to the highlight feature being toggled on and off
 *
 * @param {boolean} highlightEnabled
 */
ChangesListWrapperWidget.prototype.onHighlightChange = function ( highlightEnabled ) {
	if ( highlightEnabled ) {
		this.applyHighlight();
	} else {
		this.clearHighlight();
	}
};

/**
 * Respond to a filter item model update
 */
ChangesListWrapperWidget.prototype.onItemUpdate = function () {
	if ( this.controller.isInitialized() && this.filtersViewModel.isHighlightEnabled() ) {
		// this.controller.isInitialized() is still false during page load,
		// we don't want to clear/apply highlights at this stage.
		this.clearHighlight();
		this.applyHighlight();
	}
};

/**
 * Respond to changes list model invalidate
 */
ChangesListWrapperWidget.prototype.onModelInvalidate = function () {
	$( document.body ).addClass( 'mw-rcfilters-ui-loading' );
};

/**
 * Respond to changes list model update
 *
 * @param {jQuery|string} $changesListContent The content of the updated changes list
 * @param {jQuery} $fieldset The content of the updated fieldset
 * @param {string} noResultsDetails Type of no result error
 * @param {boolean} isInitialDOM Whether $changesListContent is the existing (already attached) DOM
 * @param {boolean} from Timestamp of the new changes
 */
ChangesListWrapperWidget.prototype.onModelUpdate = function (
	$changesListContent, $fieldset, noResultsDetails, isInitialDOM, from
) {
	mw.hook( 'rcfilters.changeslistwrapperwidget.updated' ).fire( this );
	const $message = $( '<div>' )
			.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-results' ),
		isEmpty = $changesListContent === 'NO_RESULTS',
		// For enhanced mode, we have to load this modules, which is
		// not loaded for the 'regular' mode in the backend
		loaderPromise = mw.user.options.get( 'usenewrc' ) && !OO.ui.isMobile() ?
			mw.loader.using( [ 'mediawiki.special.changeslist.enhanced' ] ) :
			$.Deferred().resolve();

	this.$element.toggleClass( 'mw-changeslist', !isEmpty );
	if ( isEmpty ) {
		this.$element.empty();

		if ( this.filtersViewModel.hasConflict() ) {
			const conflictItem = this.filtersViewModel.getFirstConflictedItem();

			$message
				.append(
					$( '<div>' )
						.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-results-conflict' )
						.text( mw.msg( 'rcfilters-noresults-conflict' ) ),
					$( '<div>' )
						.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-results-message' )
						// TODO: Document possible messages
						// eslint-disable-next-line mediawiki/msg-doc
						.text( mw.msg( conflictItem.getCurrentConflictResultMessage() ) )
				);
		} else {
			$message
				.append(
					$( '<div>' )
						.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-results-noresult' )
						// The following messages can be used here:
						// * recentchanges-noresult
						// * recentchanges-timeout
						// * recentchanges-network
						// * recentchanges-notargetpage
						// * allpagesbadtitle
						.html( mw.message( this.getMsgKeyForNoResults( noResultsDetails ) ).parse() )
				);

			// remove all classes matching mw-changeslist-*
			// eslint-disable-next-line mediawiki/class-doc
			this.$element.removeClass( ( elementIndex, allClasses ) => allClasses
				.split( ' ' )
				.filter( ( className ) => className.startsWith( 'mw-changeslist-' ) )
				.join( ' ' ) );
		}

		this.$element.append( $message );
	} else {
		if ( !isInitialDOM ) {
			this.$element.empty().append( $changesListContent );

			if ( from ) {
				this.emphasizeNewChanges( from );
			}
		}

		// Apply highlight
		this.applyHighlight();

	}

	this.$element.prepend( $( '<div>' ).addClass( 'mw-changeslist-overlay' ) );

	loaderPromise.done( () => {
		if ( !isInitialDOM && !isEmpty ) {
			// Make sure enhanced RC re-initializes correctly
			mw.hook( 'wikipage.content' ).fire( this.$element );
		}

		$( document.body ).removeClass( 'mw-rcfilters-ui-loading' );
	} );
};

/**
 * Toggles overlay class on changes list
 *
 * @param {boolean} isVisible True if overlay should be visible
 */
ChangesListWrapperWidget.prototype.toggleOverlay = function ( isVisible ) {
	this.$element.toggleClass( 'mw-rcfilters-ui-changesListWrapperWidget--overlaid', isVisible );
};

/**
 * Map a reason for having no results to its message key
 *
 * @param {string} reason One of the NO_RESULTS_* "constant" that represent
 *   a reason for having no results
 * @return {string} Key for the message that explains why there is no results in this case
 */
ChangesListWrapperWidget.prototype.getMsgKeyForNoResults = function ( reason ) {
	const reasonMsgKeyMap = {
		NO_RESULTS_NORMAL: 'recentchanges-noresult',
		NO_RESULTS_TIMEOUT: 'recentchanges-timeout',
		NO_RESULTS_NETWORK_ERROR: 'recentchanges-network',
		NO_RESULTS_NO_TARGET_PAGE: 'recentchanges-notargetpage',
		NO_RESULTS_INVALID_TARGET_PAGE: 'allpagesbadtitle'
	};
	return reasonMsgKeyMap[ reason ];
};

/**
 * Emphasize the elements (or groups) newer than the 'from' parameter
 *
 * @param {string} from Anything newer than this is considered 'new'
 */
ChangesListWrapperWidget.prototype.emphasizeNewChanges = function ( from ) {
	const selector = this.inEnhancedMode() ?
			'table.mw-enhanced-rc[data-mw-ts]' :
			'li[data-mw-ts]',
		$set = this.$element.find( selector ),
		length = $set.length;

	let $firstNew,
		$newChanges = $( [] );
	$set.each( function ( index ) {
		const $this = $( this ),
			ts = $this.data( 'mw-ts' );

		if ( ts >= from ) {
			$newChanges = $newChanges.add( $this );
			$firstNew = $this;

			// guards against putting the marker after the last element
			if ( index === ( length - 1 ) ) {
				$firstNew = null;
			}
		}
	} );

	if ( $firstNew ) {
		const $indicator = $( '<div>' )
			.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-previousChangesIndicator' );

		$firstNew.after( $indicator );
	}

	// FIXME: Use CSS transition
	// eslint-disable-next-line no-jquery/no-fade
	$newChanges
		.hide()
		.fadeIn( 1000 );
};

/**
 * In enhanced mode, we need to check whether the grouped results all have the
 * same active highlights in order to see whether the "parent" of the group should
 * be grey or highlighted normally.
 *
 * This is called every time highlights are applied.
 */
ChangesListWrapperWidget.prototype.updateEnhancedParentHighlight = function () {
	const $enhancedTopPageCell = this.$element.find( 'table.mw-enhanced-rc' );

	const activeHighlightClasses = this.filtersViewModel.getCurrentlyUsedHighlightColors().map( ( color ) => 'mw-rcfilters-highlight-color-' + color );

	// Go over top pages and their children, and figure out if all subpages have the
	// same highlights between themselves. If they do, the parent should be highlighted
	// with all colors. If classes are different, the parent should receive a grey
	// background
	$enhancedTopPageCell.each( function () {
		const $table = $( this );

		// Collect the relevant classes from the first nested child
		const firstChildClasses = activeHighlightClasses.filter(
			// eslint-disable-next-line no-jquery/no-class-state
			( className ) => $table.find( 'tr' ).eq( 2 ).hasClass( className )
		);
		// Filter the non-head rows and see if they all have the same classes
		// to the first row
		const $rowsWithDifferentHighlights = $table.find( 'tr:not(:first-child)' ).filter( function () {
			const $this = $( this );

			const classesInThisRow = activeHighlightClasses.filter(
				// eslint-disable-next-line no-jquery/no-class-state
				( className ) => $this.hasClass( className )
			);

			return !OO.compare( firstChildClasses, classesInThisRow );
		} );

		// If classes are different, tag the row for using grey color
		$table.find( 'tr:first-child' )
			.toggleClass( 'mw-rcfilters-ui-changesListWrapperWidget-enhanced-grey', $rowsWithDifferentHighlights.length > 0 );
	} );
};

/**
 * @return {boolean} Whether the changes are grouped by page
 */
ChangesListWrapperWidget.prototype.inEnhancedMode = function () {
	const enhanced = new URL( location.href ).searchParams.get( 'enhanced' );
	return ( enhanced !== null && Number( enhanced ) ) ||
		( enhanced === null && Number( mw.user.options.get( 'usenewrc' ) ) );
};

/**
 * Apply color classes based on filters highlight configuration
 */
ChangesListWrapperWidget.prototype.applyHighlight = function () {
	if ( !this.filtersViewModel.isHighlightEnabled() ) {
		return;
	}

	this.filtersViewModel.getHighlightedItems().forEach( ( filterItem ) => {
		const $elements = this.$element.find( '.' + filterItem.getCssClass() );

		// Add highlight class to all highlighted list items
		// The following classes are used here:
		// * mw-rcfilters-highlight-color-c1
		// * mw-rcfilters-highlight-color-c2
		// * mw-rcfilters-highlight-color-c3
		// * mw-rcfilters-highlight-color-c4
		// * mw-rcfilters-highlight-color-c5
		// * notheme - T366920 Makes highlighted list legible in dark-mode.
		$elements
			.addClass(
				'mw-rcfilters-highlighted ' +
				'mw-rcfilters-highlight-color-' + filterItem.getHighlightColor()
			);

		// Track the filters for each item in .data( 'highlightedFilters' )
		$elements.each( function () {
			let filters = $( this ).data( 'highlightedFilters' );
			if ( !filters ) {
				filters = [];
				$( this ).data( 'highlightedFilters', filters );
			}
			if ( !filters.includes( filterItem.getLabel() ) ) {
				filters.push( filterItem.getLabel() );
			}
		} );
	} );
	// Apply a title to each highlighted item, with a list of filters
	this.$element.find( '.mw-rcfilters-highlighted' ).each( function () {
		const filters = $( this ).data( 'highlightedFilters' );

		if ( filters && filters.length ) {
			$( this ).attr( 'title', mw.msg(
				'rcfilters-highlighted-filters-list',
				filters.join( mw.msg( 'comma-separator' ) )
			) );
		}

	} );
	if ( this.inEnhancedMode() ) {
		this.updateEnhancedParentHighlight();
	}

	// Turn on highlights
	this.$element.addClass( 'mw-rcfilters-ui-changesListWrapperWidget-highlighted' );
};

/**
 * Remove all color classes
 */
ChangesListWrapperWidget.prototype.clearHighlight = function () {
	// Remove highlight classes
	HighlightColors.forEach( ( color ) => {
		// The following classes are used here:
		// * mw-rcfilters-highlight-color-c1
		// * mw-rcfilters-highlight-color-c2
		// * mw-rcfilters-highlight-color-c3
		// * mw-rcfilters-highlight-color-c4
		// * mw-rcfilters-highlight-color-c5
		this.$element
			.find( '.mw-rcfilters-highlight-color-' + color )
			.removeClass( 'mw-rcfilters-highlight-color-' + color );
	} );

	this.$element.find( '.mw-rcfilters-highlighted' )
		.removeAttr( 'title' )
		.removeData( 'highlightedFilters' )
		.removeClass( 'mw-rcfilters-highlighted' );

	// Remove grey from enhanced rows
	this.$element.find( '.mw-rcfilters-ui-changesListWrapperWidget-enhanced-grey' )
		.removeClass( 'mw-rcfilters-ui-changesListWrapperWidget-enhanced-grey' );

	// Turn off highlights
	this.$element.removeClass( 'mw-rcfilters-ui-changesListWrapperWidget-highlighted' );
};

module.exports = ChangesListWrapperWidget;
