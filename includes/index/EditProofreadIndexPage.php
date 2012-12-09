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
 * @ingroup ProofreadPage
 */

class EditProofreadIndexPage extends EditPage {

	protected function isSectionEditSupported() {
		return false; // sections and forms don't mix
	}

	/**
	 * Add custom fields
	 */
	protected function showContentForm() {
		global $wgOut;

		$pageLang = $this->mTitle->getPageLanguage();
		$inputAttributes = array( 'lang' => $pageLang->getCode(), 'dir' => $pageLang->getDir() );
		if( wfReadOnly() === true ) {
			$inputAttributes['readonly'] = '';
		}

		$index = new ProofreadIndexPage( $this->mTitle, $this->textbox1 );
		$entries = $index->getIndexEntries();

		$wgOut->addHTML( Xml::openElement( 'table', array( 'id' => 'prp-formTable' ) ) );
		$i = 10;
		foreach( $entries as $entry ) {
			$inputAttributes['tabindex'] = $i;
			$this->addEntry( $entry, $inputAttributes );
			$i++;
		}
		$wgOut->addHTML( Xml::closeElement( 'table' ) );
	}

	/**
	 * Add an entry to the form
	 *
	 * @param $entry ProofreadIndexEntry
	 */
	protected function addEntry( ProofreadIndexEntry $entry, $inputAttributes = array() ) {
		global $wgOut;

		if ( $entry->isHidden() ) {
			return;
		}
		$key = $this->getFieldNameForEntry( $entry->getKey() );
		$val = $this->safeUnicodeOutput( $entry->getStringValue() );

		$wgOut->addHTML(
			Xml::openElement( 'tr' ) .
				Xml::openElement( 'th', array( 'scope' => 'row' ) ) .
					Xml::label( $entry->getLabel(), $key )
		);


		$help = $entry->getHelp();
		if ( $help !== '' ) {
			$wgOut->addHTML( Xml::element( 'span', array( 'title' => $help, 'class' => 'prp-help-field' ) ) );
		}

		$wgOut->addHTML(
			Xml::closeElement( 'th' ) .
			Xml::openElement( 'td' )
		);

		$values = $entry->getPossibleValues();
		if ( $values !== null ) {
			$select = new XmlSelect( $key, $key, $val );
			foreach( $values as $value => $label ) {
				$select->addOption( $label, $value );
			}
			if ( !isset( $values[$val] ) && $val !== '' ) { //compatiblity with already set data that aren't in the list
				$select->addOption( $val, $val );
			}
			$wgOut->addHTML( $select->getHtml() );
		} else {
			$type = $entry->getType();
			$inputType = ( $type === 'number' ) ? 'number' : 'text';
			$size = $entry->getSize();
			$inputAttributes['class'] = 'prp-input-' . $type;

			if ( $size === 1 ) {
				$inputAttributes['type'] = $inputType;
				$inputAttributes['id'] = $key;
				$wgOut->addHTML( Xml::input( $key, 60, $val, $inputAttributes ) );
			} else {
				$wgOut->addHTML( Xml::textarea( $key, $val, 60, $size, $inputAttributes ) );
			}
		}

		$wgOut->addHTML(
				Xml::closeElement( 'td' ) .
			Xml::closeElement( 'tr' )
		);
	}

	/**
	 * Return the name of the edit field for an entry
	 *
	 * @param $key string the entry key
	 */
	protected function getFieldNameForEntry( $key ) {
		return 'wpprpindex-' . $key;
	}

	/**
	 * Init $this->textbox1 from form content
	 *
	 * @param $request WebRequest
	 */
	protected function importContentFormData( &$request ) {
		$config = ProofreadIndexPage::getDataConfig();
		$this->textbox1 = "{{:MediaWiki:Proofreadpage_index_template";
		foreach( $config as $key => $params ) {
			$field = $this->getFieldNameForEntry( $key );
			$value = $this->cleanInputtedContent( $request->getVal( $field ) );
			$entry = new ProofreadIndexEntry( $key, $value, $params );
			if( !$entry->isHidden() ) {
				$this->textbox1 .= "\n|" . $entry->getKey() . "=" . $entry->getStringValue();
			}
		}
		$this->textbox1 .= "\n}}";
	}

	/**
	 * Clean a text before inclusion into a template
	 *
	 * @param $value string
	 * @return string
	 */
	protected function cleanInputtedContent( $value ) {
		// remove trailing \n \t or \r
		$value = trim( $value );

		// replace pipe symbol everywhere...
		$value = preg_replace( '#\|#', '&!&', $value );

		// ...except in links...
		$prev = '';
		do {
			$prev = $value;
			$value = preg_replace( '#\[\[(.*?)&!&(.*?)\]\]#', '[[$1|$2]]', $value );
		} while ( $value != $prev );

		// ..and in templates
		do {
			$prev = $value;
			$value = preg_replace( '#\{\{(.*?)&!&(.*?)\}\}#', '{{$1|$2}}', $value );
		} while ( $value != $prev );

		$value = preg_replace( '#&!&#', '{{!}}', $value );

		return $value;
	}
}
