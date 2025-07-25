<?php

/**
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
 * @ingroup Installer
 */

namespace MediaWiki\Installer;

use MediaWiki\Html\Html;
use MediaWiki\Specials\SpecialVersion;
use Wikimedia\IPUtils;

class WebInstallerOptions extends WebInstallerPage {

	/**
	 * @return string|null
	 */
	public function execute() {
		if ( $this->getVar( '_SkipOptional' ) == 'skip' ) {
			$this->submitSkins();
			return 'skip';
		}
		if ( $this->parent->request->wasPosted() && $this->submit() ) {
			return 'continue';
		}

		$this->startForm();
		$this->addModeOptions();
		$this->addEmailOptions();
		$this->addSkinOptions();
		$this->addExtensionOptions();
		$this->addFileOptions();
		$this->addPersonalizationOptions();
		$this->addAdvancedOptions();
		$this->endForm();

		return null;
	}

	private function addPersonalizationOptions() {
		$parent = $this->parent;
		$this->addHTML(
			$this->getFieldsetStart( 'config-personalization-settings' ) .
			Html::rawElement( 'div', [
				'class' => 'config-drag-drop'
			], wfMessage( 'config-logo-summary' )->parse() ) .
			Html::openElement( 'div', [
				'class' => 'config-personalization-options'
			] ) .
			Html::hidden( 'config_LogoSiteName', $this->getVar( 'wgSitename' ) ) .
			$parent->getTextBox( [
				'var' => '_LogoIcon',
				// Single quotes are intentional, LocalSettingsGenerator must output this unescaped.
				'value' => '$wgResourceBasePath/resources/assets/change-your-logo.svg',
				'label' => 'config-logo-icon',
				'attribs' => [ 'dir' => 'ltr' ],
				'help' => $parent->getHelpBox( 'config-logo-icon-help' )
			] ) .
			$parent->getTextBox( [
				'var' => '_LogoWordmark',
				'label' => 'config-logo-wordmark',
				'attribs' => [ 'dir' => 'ltr' ],
				'help' => $parent->getHelpBox( 'config-logo-wordmark-help' )
			] ) .
			$parent->getTextBox( [
				'var' => '_LogoTagline',
				'label' => 'config-logo-tagline',
				'attribs' => [ 'dir' => 'ltr' ],
				'help' => $parent->getHelpBox( 'config-logo-tagline-help' )
			] ) .
			$parent->getTextBox( [
				'var' => '_Logo1x',
				'label' => 'config-logo-sidebar',
				'attribs' => [ 'dir' => 'ltr' ],
				'help' => $parent->getHelpBox( 'config-logo-sidebar-help' )
			] ) .
			Html::openElement( 'div', [
				'class' => 'logo-preview-area',
				'data-main-page' => wfMessage( 'config-logo-preview-main' ),
				'data-filedrop' => wfMessage( 'config-logo-filedrop' )
			] ) .
			Html::closeElement( 'div' ) .
			Html::closeElement( 'div' ) .
			$this->getFieldsetEnd()
		);
	}

	/**
	 * Wiki mode - user rights and copyright model.
	 */
	private function addModeOptions(): void {
		$this->addHTML(
			# User Rights
			// getRadioSet() builds a set of labeled radio buttons.
			// For grep: The following messages are used as the item labels:
			// config-profile-wiki, config-profile-no-anon, config-profile-fishbowl, config-profile-private
			$this->parent->getRadioSet( [
				'var' => '_RightsProfile',
				'label' => 'config-profile',
				'itemLabelPrefix' => 'config-profile-',
				'values' => array_keys( $this->parent->rightsProfiles ),
			] ) .
			$this->parent->getInfoBox( wfMessage( 'config-profile-help' )->plain() ) .

			# Licensing
			// getRadioSet() builds a set of labeled radio buttons.
			// For grep: The following messages are used as the item labels:
			// config-license-cc-by, config-license-cc-by-sa, config-license-cc-by-nc-sa,
			// config-license-cc-0, config-license-pd, config-license-gfdl,
			// config-license-none
			$this->parent->getRadioSet( [
				'var' => '_LicenseCode',
				'label' => 'config-license',
				'itemLabelPrefix' => 'config-license-',
				'values' => array_keys( $this->parent->licenses ),
				'commonAttribs' => [ 'class' => 'licenseRadio' ],
			] ) .
			$this->parent->getHelpBox( 'config-license-help' )
		);
	}

	/**
	 * User email options.
	 */
	private function addEmailOptions(): void {
		$emailwrapperStyle = $this->getVar( 'wgEnableEmail' ) ? '' : 'display: none';
		$this->addHTML(
			$this->getFieldsetStart( 'config-email-settings' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEnableEmail',
				'label' => 'config-enable-email',
				'attribs' => [ 'class' => 'showHideRadio', 'rel' => 'emailwrapper' ],
			] ) .
			$this->parent->getHelpBox( 'config-enable-email-help' ) .
			"<div id=\"emailwrapper\" style=\"$emailwrapperStyle\">" .
			$this->parent->getTextBox( [
				'var' => 'wgPasswordSender',
				'label' => 'config-email-sender'
			] ) .
			$this->parent->getHelpBox( 'config-email-sender-help' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEnableUserEmail',
				'label' => 'config-email-user',
			] ) .
			$this->parent->getHelpBox( 'config-email-user-help' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEnotifUserTalk',
				'label' => 'config-email-usertalk',
			] ) .
			$this->parent->getHelpBox( 'config-email-usertalk-help' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEnotifWatchlist',
				'label' => 'config-email-watchlist',
			] ) .
			$this->parent->getHelpBox( 'config-email-watchlist-help' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEmailAuthentication',
				'label' => 'config-email-auth',
			] ) .
			$this->parent->getHelpBox( 'config-email-auth-help' ) .
			"</div>" .
			$this->getFieldsetEnd()
		);
	}

	/**
	 * Opt-in for bundled skins.
	 */
	private function addSkinOptions(): void {
		$skins = $this->parent->findExtensions( 'skins' )->value;
		'@phan-var array[] $skins';
		$skinHtml = $this->getFieldsetStart( 'config-skins' );

		$skinNames = array_map( 'strtolower', array_keys( $skins ) );
		$chosenSkinName = $this->getVar( 'wgDefaultSkin', $this->parent->getDefaultSkin( $skinNames ) );

		if ( $skins ) {
			$radioButtons = $this->parent->getRadioElements( [
				'var' => 'wgDefaultSkin',
				'itemLabels' => array_fill_keys( $skinNames, 'config-skins-use-as-default' ),
				'values' => $skinNames,
				'value' => $chosenSkinName,
			] );

			foreach ( $skins as $skin => $info ) {
				if ( isset( $info['screenshots'] ) ) {
					$screenshotText = $this->makeScreenshotsLink( $skin, $info['screenshots'] );
				} else {
					$screenshotText = htmlspecialchars( $skin );
				}
				$skinHtml .=
					'<div class="config-skins-item">' .
					$this->parent->getCheckBox( [
						'var' => "skin-$skin",
						'rawtext' => $screenshotText . $this->makeMoreInfoLink( $info ),
						'value' => $this->getVar( "skin-$skin", true ), // all found skins enabled by default
					] ) .
					'<div class="config-skins-use-as-default">' . $radioButtons[strtolower( $skin )] . '</div>' .
					'</div>';
			}
		} else {
			$skinHtml .=
				Html::warningBox( wfMessage( 'config-skins-missing' )->parse(), 'config-warning-box' ) .
				Html::hidden( 'config_wgDefaultSkin', $chosenSkinName );
		}

		$skinHtml .= $this->parent->getHelpBox( 'config-skins-help' ) .
			$this->getFieldsetEnd();
		$this->addHTML( $skinHtml );
	}

	/**
	 * Opt-in for bundled extensions.
	 */
	private function addExtensionOptions(): void {
		global $wgLang;

		$extensions = $this->parent->findExtensions()->value;
		'@phan-var array[] $extensions';
		$dependencyMap = [];

		if ( $extensions ) {
			$extHtml = $this->getFieldsetStart( 'config-extensions' );

			$extByType = [];
			$types = SpecialVersion::getExtensionTypes();
			// Sort by type first
			foreach ( $extensions as $ext => $info ) {
				if ( !isset( $info['type'] ) || !isset( $types[$info['type']] ) ) {
					// We let extensions normally define custom types, but
					// since we aren't loading extensions, we'll have to
					// categorize them under other
					$info['type'] = 'other';
				}
				$extByType[$info['type']][$ext] = $info;
			}

			foreach ( $types as $type => $message ) {
				if ( !isset( $extByType[$type] ) ) {
					continue;
				}
				$extHtml .= Html::element( 'h2', [], $message );
				foreach ( $extByType[$type] as $ext => $info ) {
					$attribs = [
						'data-name' => $ext,
						'class' => 'config-ext-input cdx-checkbox__input'
					];
					$labelAttribs = [];
					if ( isset( $info['requires']['extensions'] ) ) {
						$dependencyMap[$ext]['extensions'] = $info['requires']['extensions'];
						$labelAttribs['class'] = 'mw-ext-with-dependencies';
					}
					if ( isset( $info['requires']['skins'] ) ) {
						$dependencyMap[$ext]['skins'] = $info['requires']['skins'];
						$labelAttribs['class'] = 'mw-ext-with-dependencies';
					}
					if ( isset( $dependencyMap[$ext] ) ) {
						$links = [];
						// For each dependency, link to the checkbox for each
						// extension/skin that is required
						if ( isset( $dependencyMap[$ext]['extensions'] ) ) {
							foreach ( $dependencyMap[$ext]['extensions'] as $name ) {
								$links[] = Html::element(
									'a',
									[ 'href' => "#config_ext-$name" ],
									$name
								);
							}
						}
						if ( isset( $dependencyMap[$ext]['skins'] ) ) {
							// @phan-suppress-next-line PhanTypeMismatchForeach Phan internal bug
							foreach ( $dependencyMap[$ext]['skins'] as $name ) {
								$links[] = Html::element(
									'a',
									[ 'href' => "#config_skin-$name" ],
									$name
								);
							}
						}

						$text = wfMessage( 'config-extensions-requires', $ext )
							->rawParams( $wgLang->commaList( $links ) )
							->escaped();
					} else {
						$text = htmlspecialchars( $ext );
					}
					$extHtml .= $this->parent->getCheckBox( [
						'var' => "ext-$ext",
						'rawtext' => $text . $this->makeMoreInfoLink( $info ),
						'attribs' => $attribs,
						'labelAttribs' => $labelAttribs,
					] );
				}
			}

			$extHtml .= $this->parent->getHelpBox( 'config-extensions-help' ) .
				$this->getFieldsetEnd();
			$this->addHTML( $extHtml );
			// Push the dependency map to the client side
			$this->addHTML( Html::inlineScript(
				'var extDependencyMap = ' . Html::encodeJsVar( $dependencyMap )
			) );
		}
	}

	/**
	 * Image and file upload options.
	 */
	private function addFileOptions(): void {
		// Having / in paths in Windows looks funny :)
		$this->setVar( 'wgDeletedDirectory',
			str_replace(
				'/', DIRECTORY_SEPARATOR,
				$this->getVar( 'wgDeletedDirectory' )
			)
		);

		$uploadwrapperStyle = $this->getVar( 'wgEnableUploads' ) ? '' : 'display: none';
		$this->addHTML(
			# Uploading
			$this->getFieldsetStart( 'config-upload-settings' ) .
			$this->parent->getCheckBox( [
				'var' => 'wgEnableUploads',
				'label' => 'config-upload-enable',
				'attribs' => [ 'class' => 'showHideRadio', 'rel' => 'uploadwrapper' ],
				'help' => $this->parent->getHelpBox( 'config-upload-help' )
			] ) .
			'<div id="uploadwrapper" style="' . $uploadwrapperStyle . '">' .
			$this->parent->getTextBox( [
				'var' => 'wgDeletedDirectory',
				'label' => 'config-upload-deleted',
				'attribs' => [ 'dir' => 'ltr' ],
				'help' => $this->parent->getHelpBox( 'config-upload-deleted-help' )
			] ) .
			'</div>'
		);
		$this->addHTML(
			$this->parent->getCheckBox( [
				'var' => 'wgUseInstantCommons',
				'label' => 'config-instantcommons',
				'help' => $this->parent->getHelpBox( 'config-instantcommons-help' )
			] ) .
			$this->getFieldsetEnd()
		);
	}

	/**
	 * System administration related options.
	 */
	private function addAdvancedOptions(): void {
		$caches = [ 'none' ];
		$cachevalDefault = 'none';

		if ( count( $this->getVar( '_Caches' ) ) ) {
			// A CACHE_ACCEL implementation is available
			$caches[] = 'accel';
			$cachevalDefault = 'accel';
		}
		$caches[] = 'memcached';

		// We'll hide/show this on demand when the value changes, see config.js.
		$cacheval = $this->getVar( '_MainCacheType' );
		if ( !$cacheval ) {
			// We need to set a default here; but don't hardcode it
			// or we lose it every time we reload the page for validation
			// or going back!
			$cacheval = $cachevalDefault;
		}
		$hidden = ( $cacheval == 'memcached' ) ? '' : 'display: none';
		$this->addHTML(
			# Advanced settings
			$this->getFieldsetStart( 'config-advanced-settings' ) .
			# Object cache settings
			// getRadioSet() builds a set of labeled radio buttons.
			// For grep: The following messages are used as the item labels:
			// config-cache-none, config-cache-accel, config-cache-memcached
			$this->parent->getRadioSet( [
				'var' => '_MainCacheType',
				'label' => 'config-cache-options',
				'itemLabelPrefix' => 'config-cache-',
				'values' => $caches,
				'value' => $cacheval,
			] ) .
			$this->parent->getHelpBox( 'config-cache-help' ) .
			"<div id=\"config-memcachewrapper\" style=\"$hidden\">" .
			$this->parent->getTextArea( [
				'var' => '_MemCachedServers',
				'label' => 'config-memcached-servers',
				'help' => $this->parent->getHelpBox( 'config-memcached-help' )
			] ) .
			'</div>' .
			$this->getFieldsetEnd()
		);
	}

	/**
	 * @param string $name
	 * @param array $screenshots
	 * @return string HTML
	 */
	private function makeScreenshotsLink( $name, $screenshots ) {
		global $wgLang;
		if ( count( $screenshots ) > 1 ) {
			$links = [];
			$counter = 1;

			foreach ( $screenshots as $shot ) {
				$links[] = Html::element(
					'a',
					[ 'href' => $shot, 'target' => '_blank' ],
					$wgLang->formatNum( $counter++ )
				);
			}
			return wfMessage( 'config-skins-screenshots', $name )
				->rawParams( $wgLang->commaList( $links ) )
				->escaped();
		} else {
			$link = Html::element(
				'a',
				[ 'href' => $screenshots[0], 'target' => '_blank' ],
				wfMessage( 'config-screenshot' )->text()
			);
			return wfMessage( 'config-skins-screenshot', $name )->rawParams( $link )->escaped();
		}
	}

	/**
	 * @param array $info
	 * @return string HTML
	 */
	private function makeMoreInfoLink( $info ) {
		if ( !isset( $info['url'] ) ) {
			return '';
		}
		return ' ' . wfMessage( 'parentheses' )->rawParams(
			Html::element(
				'a',
				[ 'href' => $info['url'] ],
				wfMessage( 'config-ext-skins-more-info' )->text()
			)
		)->escaped();
	}

	/**
	 * If the user skips this installer page, we still need to set up the default skins, but ignore
	 * everything else.
	 *
	 * @return bool
	 */
	public function submitSkins() {
		$skins = array_keys( $this->parent->findExtensions( 'skins' )->value );
		$this->parent->setVar( '_Skins', $skins );

		if ( $skins ) {
			$skinNames = array_map( 'strtolower', $skins );
			$this->parent->setVar( 'wgDefaultSkin', $this->parent->getDefaultSkin( $skinNames ) );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function submit() {
		$this->parent->setVarsFromRequest( [ '_RightsProfile', '_LicenseCode',
			'wgEnableEmail', 'wgPasswordSender', 'wgEnableUploads',
			'_Logo1x', '_LogoWordmark', '_LogoTagline', '_LogoIcon',
			'wgEnableUserEmail', 'wgEnotifUserTalk', 'wgEnotifWatchlist',
			'wgEmailAuthentication', '_MainCacheType', '_MemCachedServers',
			'wgUseInstantCommons', 'wgDefaultSkin' ] );

		$retVal = true;

		if ( !array_key_exists( $this->getVar( '_RightsProfile' ), $this->parent->rightsProfiles ) ) {
			$this->setVar( '_RightsProfile', array_key_first( $this->parent->rightsProfiles ) );
		}

		$code = $this->getVar( '_LicenseCode' );
		if ( array_key_exists( $code, $this->parent->licenses ) ) {
			// Messages:
			// config-license-cc-by, config-license-cc-by-sa, config-license-cc-by-nc-sa,
			// config-license-cc-0, config-license-pd, config-license-gfdl, config-license-none
			$entry = $this->parent->licenses[$code];
			$this->setVar( 'wgRightsText',
				$entry['text'] ?? wfMessage( 'config-license-' . $code )->text() );
			$this->setVar( 'wgRightsUrl', $entry['url'] );
			$this->setVar( 'wgRightsIcon', $entry['icon'] );
		} else {
			$this->setVar( 'wgRightsText', '' );
			$this->setVar( 'wgRightsUrl', '' );
			$this->setVar( 'wgRightsIcon', '' );
		}

		$skinsAvailable = array_keys( $this->parent->findExtensions( 'skins' )->value );
		$skinsToInstall = [];
		foreach ( $skinsAvailable as $skin ) {
			$this->parent->setVarsFromRequest( [ "skin-$skin" ] );
			if ( $this->getVar( "skin-$skin" ) ) {
				$skinsToInstall[] = $skin;
			}
		}
		$this->parent->setVar( '_Skins', $skinsToInstall );

		if ( !$skinsToInstall && $skinsAvailable ) {
			$this->parent->showError( 'config-skins-must-enable-some' );
			$retVal = false;
		}
		$defaultSkin = $this->getVar( 'wgDefaultSkin' );
		$skinsToInstallLowercase = array_map( 'strtolower', $skinsToInstall );
		if ( $skinsToInstall && !in_array( $defaultSkin, $skinsToInstallLowercase ) ) {
			$this->parent->showError( 'config-skins-must-enable-default' );
			$retVal = false;
		}

		$extsAvailable = array_keys( $this->parent->findExtensions()->value );
		$extsToInstall = [];
		foreach ( $extsAvailable as $ext ) {
			$this->parent->setVarsFromRequest( [ "ext-$ext" ] );
			if ( $this->getVar( "ext-$ext" ) ) {
				$extsToInstall[] = $ext;
			}
		}
		$this->parent->setVar( '_Extensions', $extsToInstall );

		if ( $this->getVar( '_MainCacheType' ) == 'memcached' ) {
			$memcServers = explode( "\n", $this->getVar( '_MemCachedServers' ) );
			// FIXME: explode() will always result in an array of at least one string, even on null (when
			// the string will be empty and you'll get a PHP warning), so this has never worked?
			// @phan-suppress-next-line PhanImpossibleCondition
			if ( !$memcServers ) {
				$this->parent->showError( 'config-memcache-needservers' );
				$retVal = false;
			}

			foreach ( $memcServers as $server ) {
				$memcParts = explode( ":", $server, 2 );
				if ( !isset( $memcParts[0] )
					|| ( !IPUtils::isValid( $memcParts[0] )
						&& ( gethostbyname( $memcParts[0] ) == $memcParts[0] ) )
				) {
					$this->parent->showError( 'config-memcache-badip', $memcParts[0] );
					$retVal = false;
				} elseif ( !isset( $memcParts[1] ) ) {
					$this->parent->showError( 'config-memcache-noport', $memcParts[0] );
					$retVal = false;
				} elseif ( $memcParts[1] < 1 || $memcParts[1] > 65535 ) {
					$this->parent->showError( 'config-memcache-badport', 1, 65535 );
					$retVal = false;
				}
			}
		}

		return $retVal;
	}

}
