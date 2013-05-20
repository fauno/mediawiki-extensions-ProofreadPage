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
 * @ingroup Extensions
 */

/**
 * Class that contain init system of the ProofreadPage extension
 */
class ProofreadPageInit {

	/**
	* the default namespace id for each namespaces
	* Called by the SetupAfterCache hook
	*/
	protected static $defaultNamespaceIds = array(
		'page' => 250,
		'index' => 252
	);

	/**
	 * init namespaces used by ProofreadPage
	 * @return bool false if there is an error, true if not
	 */
	public static function initNamespaces() {
		self::initNamespace( 'page' );
		self::initNamespace( 'index' );
		return true;
	}

	/**
	 * Create a namespace and his discussion one
	 * @param $key string the key of the namespace in the i18n file
	 */
	protected static function initNamespace( $key ) {
		global $wgExtraNamespaces, $wgProofreadPageNamespaceIds;

		if ( isset( $wgProofreadPageNamespaceIds[$key] ) ) {
			if ( !is_numeric( $wgProofreadPageNamespaceIds[$key] ) ) {
				die( '$wgProofreadPageNamespaceIds[' . $key . '] must be a number.' );
			}

			if ( !isset( $wgExtraNamespaces[$wgProofreadPageNamespaceIds[$key]] ) ) {
				self::createNamespace( $wgProofreadPageNamespaceIds[$key], $key );
			}
		} else { //try to find if a namespace with a known name is set (for backward compatibility)
			$id = self::getNamespaceIdForDefaultName( $key );
			if ( $id !== false ) {
				$wgProofreadPageNamespaceIds[$key] = $id;
			} else {
				if ( self::createNamespace( self::$defaultNamespaceIds[$key], $key ) ) {
					$wgProofreadPageNamespaceIds[$key] = self::$defaultNamespaceIds[$key];
				} //else: the relevant error message is output by getNamespaceId
			}
		}
	}

	/**
	 * Find if a namespace with the default name is already set (for backward compatibility) and return his id
	 * @param $key string the key of the namespace in the i18n file
	 * @return int|bool the id of the namespace or false if it doesn't exist
	 */
	protected static function getNamespaceIdForDefaultName( $key ) {
		global $wgExtraNamespaces;

		$xNamespaces = array();
		foreach ( $wgExtraNamespaces as $i => $text ) {
			$xNamespaces[strtolower( $text )] = $i;
		}

		$name = strtolower( self::getNamespaceName( $key ) );

		return array_key_exists( $name, $xNamespaces ) ? $xNamespaces[$name] : false;
	}

	/**
	 * Create a namespace and his discussion one
	 * @param $id integer the namespace id
	 * @param $key string the key of the namespace in the i18n file
	 * @return bool false if there is an error, true if not
	 */
	protected static function createNamespace( $id, $key ) {
		global $wgCanonicalNamespaceNames, $wgExtraNamespaces;

		if ( isset( $wgExtraNamespaces[$id] ) || isset( $wgExtraNamespaces[$id + 1] ) ) {
			return false;
		}

		$wgExtraNamespaces[$id] = self::getNamespaceName( $key );
		$wgExtraNamespaces[$id + 1] = self::getNamespaceName( $key . '_talk' );
		$wgCanonicalNamespaceNames[$id] = $wgExtraNamespaces[$id]; //Very hugly but needed because initNamespaces() is called after the add of $wgExtraNamespaces into $wgCanonicalNamespaceNames
		$wgCanonicalNamespaceNames[$id + 1] = $wgExtraNamespaces[$id + 1];
		$wgNamespaceAliases[$id] = self::getNamespaceName( $key, 'en' );
		$wgNamespaceAliases[$id + 1] = self::getNamespaceName( $key . '_talk', 'en' );
		return true;
	}

	/**
	 * Return the internationalized name of a namespace as set in proofreadPageNamespacesNames.
	 * The english language is used as fallback.
	 * @param $key string namespace key in the array
	 * @param $lang string language code by default the wiki language
	 * @return array
	 */
	protected static function getNamespaceName( $key, $lang = '' ) {
		global $proofreadPageNamespacesNames;

		if ( $lang === '' ) {
			global $wgLanguageCode;
			$lang = $wgLanguageCode;
		}
		return isset( $proofreadPageNamespacesNames[$lang][$key] )
				? $proofreadPageNamespacesNames[$lang][$key]
				: $proofreadPageNamespacesNames['en'][$key];
	}

	/**
	 * Get the id of the namespace. Required that Mediawiki is loaded and ProofreadPageInit::initNamespace has been executed for the relevant namespace.
	 * Warning: It's not the function you search. If you want to know the index or page namespace id use ProofreadPage::getIndexNamespaceId() or ProofreadPage::getPageNamespaceId()
	 * @param $key string the key of the namespace in the i18n file
	 * @param $configName string the name of the mediawiki configuration entry that store the name of the namespace
	 * @return int
	 */
	public static function getNamespaceId( $key, $configName ) {
		global $wgProofreadPageNamespaceIds;

		$namespaceText = strtolower( str_replace( ' ', '_', wfMessage( $configName )->inContentLanguage()->plain() ) );
		$namespace = MWNamespace::getCanonicalIndex( $namespaceText );
		if ( $namespace === null ) {
			if ( !isset( $wgProofreadPageNamespaceIds[$key] ) ) {
				die( 'Namespace with id ' . self::$defaultNamespaceIds[$key] . ' is already set ! ProofreadPage can\'t use his id in order to create ' . self::getNamespaceName( $key, 'en' ) . ' namespace. Update your LocalSettings.php adding $wgProofreadPageNamespaceIds[' . $key . '] = /* NUMERICAL ID OF THE ' . self::getNamespaceName( $key, 'en' ) . ' NAMESPACE */; AFTER the inclusion of Proofread Page' ); //The only case where there $globalId is not set is when a namespace with the default id already exist and is not a prp namespace.
			}
			return $wgProofreadPageNamespaceIds[$key];
		} else {
			if ( isset( $wgProofreadPageNamespaceIds[$key] ) && $namespace != $wgProofreadPageNamespaceIds[$key] ) {
				die( 'There is a bug in mediawiki configuration: the ProofreadPage ' . self::getNamespaceName( $key, 'en' ) . ' namespace is set to be number ' . $wgProofreadPageNamespaceIds[$key] . ' in the $wgProofreadPageNamespaceIds[' . $key . '] PHP configuration parameter and number ' . $namespace . ' in the page [[Mediawiki:' . $configName . ']]. Update your LocalSettings.php adding $wgProofreadPageNamespaceIds[' . $key . '] = /* NUMERICAL ID OF THE ' . self::getNamespaceName( $key, 'en' ) . ' NAMESPACE */; AFTER the inclusion of Proofread Page and delete [[Mediawiki:' . $configName . ']] page.' );
			}
			return $namespace;
		}
	}
}
