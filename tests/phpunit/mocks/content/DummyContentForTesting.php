<?php

use MediaWiki\Content\AbstractContent;
use MediaWiki\Content\Content;

class DummyContentForTesting extends AbstractContent {

	public const MODEL_ID = "testing";

	/** @var mixed */
	private $data;

	public function __construct( mixed $data ) {
		parent::__construct( self::MODEL_ID );

		$this->data = $data;
	}

	/** @inheritDoc */
	public function serialize( $format = null ) {
		return $this->data;
	}

	/**
	 * @return string A string representing the content in a way useful for
	 *   building a full text search index. If no useful representation exists,
	 *   this method returns an empty string.
	 */
	public function getTextForSearchIndex() {
		return '';
	}

	/**
	 * @return string|bool The wikitext to include when another page includes this  content,
	 *  or false if the content is not includable in a wikitext page.
	 */
	public function getWikitextForTransclusion() {
		return false;
	}

	/**
	 * Returns a textual representation of the content suitable for use in edit
	 * summaries and log messages.
	 *
	 * @param int $maxlength Maximum length of the summary text.
	 * @return string The summary text.
	 */
	public function getTextForSummary( $maxlength = 250 ) {
		return '';
	}

	/**
	 * Returns native representation of the data. Interpretation depends on the data model used,
	 * as given by getDataModel().
	 *
	 * @return mixed The native representation of the content. Could be a string, a nested array
	 *  structure, an object, a binary blob... anything, really.
	 */
	public function getNativeData() {
		return $this->data;
	}

	/**
	 * returns the content's nominal size in bogo-bytes.
	 *
	 * @return int
	 */
	public function getSize() {
		return strlen( $this->data );
	}

	/**
	 * Return a copy of this Content object. The following must be true for the object returned
	 * if $copy = $original->copy()
	 *
	 * * get_class($original) === get_class($copy)
	 * * $original->getModel() === $copy->getModel()
	 * * $original->equals( $copy )
	 *
	 * If and only if the Content object is immutable, the copy() method can and should
	 * return $this. That is,  $copy === $original may be true, but only for imutable content
	 * objects.
	 *
	 * @return Content A copy of this object
	 */
	public function copy() {
		return $this;
	}

	/**
	 * Returns true if this content is countable as a "real" wiki page, provided
	 * that it's also in a countable location (e.g. a current revision in the main namespace).
	 *
	 * @param bool|null $hasLinks If it is known whether this content contains links,
	 * provide this information here, to avoid redundant parsing to find out.
	 * @return bool
	 */
	public function isCountable( $hasLinks = null ) {
		return false;
	}
}
