/*!
 * MediaWiki Widgets - TagMultiselectWidget class.
 *
 * @copyright 2017 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */
( function () {
	/**
	 * @classdesc Input list of tags in a single line.
	 *
	 * This extends TagMultiselectWidget by adding an invisible textarea
	 * element which will be used to submit the values of the tags
	 *
	 * If used inside HTML form the results will be sent as the list of
	 * newline-separated tags.
	 *
	 * @class
	 * @extends OO.ui.TagMultiselectWidget
	 *
	 * @constructor
	 * @description Create an instance of `mw.widgets.TagMultiselectWidget`.
	 * @param {Object} [config] Configuration options
	 * @param {string} [config.name] Name of input to submit results (when used in HTML forms)
	 */
	mw.widgets.TagMultiselectWidget = function MwWidgetsTagMultiselectWidget( config ) {
		// Parent constructor
		mw.widgets.TagMultiselectWidget.super.call( this, $.extend( {}, config, {} ) );

		if ( 'name' in config ) {
			// Use this instead of <input type="hidden">, because hidden inputs do not have separate
			// 'value' and 'defaultValue' properties.
			this.$hiddenInput = $( '<textarea>' )
				.addClass( 'oo-ui-element-hidden' )
				.attr( 'name', config.name )
				.appendTo( this.$element );
			// Update with preset values
			this.updateHiddenInput();
			// Set the default value (it might be different from just being empty)
			this.$hiddenInput.prop( 'defaultValue', this.getValue().join( '\n' ) );
		}

		// Events
		// When list of selected tags changes, update hidden input
		this.connect( this, {
			change: 'updateHiddenInput'
		} );
	};

	/* Setup */

	OO.inheritClass( mw.widgets.TagMultiselectWidget, OO.ui.TagMultiselectWidget );

	/* Methods */

	/**
	 * If used inside HTML form, then update hiddenInput with list of
	 * newline-separated tags.
	 *
	 * @private
	 */
	mw.widgets.TagMultiselectWidget.prototype.updateHiddenInput = function () {
		if ( '$hiddenInput' in this ) {
			this.$hiddenInput.val( this.getValue().join( '\n' ) );
			// Trigger a 'change' event as if a user edited the text
			// (it is not triggered when changing the value from JS code).
			this.$hiddenInput.trigger( 'change' );
		}
	};

}() );
