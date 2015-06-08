<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Filter extends Fishpig_Wordpress_Helper_Abstract
{
	/**
	 * Applies a set of filters to the given string
	 *
	 * @param string $content
	 * @param Fishpig_Wordpress_Model_Post_Abstract $object
	 * @param string $context
	 * @return string
	 */
	public function applyFilters($content, $object, $context)
	{
		$contentObj = new Varien_Object(array(
			'content' => trim(preg_replace('/(&nbsp;)$/', '', trim($content)))
		));

		Mage::dispatchEvent('wordpress_string_filter_before', array('content' => $contentObj, 'object' => $object, 'context' => $context, 'helper' => $this));
		
		$content = $contentObj->getContent();

		$this->_applyShortcodes($content, $object, $context);
		$this->_addMagentoFilters($content);

		if (Mage::getStoreConfigFlag('wordpress/misc/autop')) {
			$this->_addParagraphsToString($content);
		}
		
		$contentObj = new Varien_Object(array('content' => $content));
				
		Mage::dispatchEvent('wordpress_string_filter_after', array('content' => $contentObj, 'object' => $object, 'context' => $context, 'helper' => $this));

		$content = $contentObj->getContent();
	
		return $content;
	}

	/**
	 * Apply shortcodes to the content
	 *
	 * @param string &$content
	 * @param Fishpig_Wordpress_Model_Post_Abstract $object
	 */
	protected function _applyShortcodes(&$content, $object, $context)
	{
		Mage::helper('wordpress/shortcode_gist')->apply($content, $object);
		Mage::helper('wordpress/shortcode_scribd')->apply($content, $object);
		Mage::helper('wordpress/shortcode_dailymotion')->apply($content, $object);
		Mage::helper('wordpress/shortcode_vimeo')->apply($content, $object);
		Mage::helper('wordpress/shortcode_instagram')->apply($content, $object);
		Mage::helper('wordpress/shortcode_youtube')->apply($content, $object);
		Mage::helper('wordpress/shortcode_product')->apply($content, $object);
		Mage::helper('wordpress/shortcode_caption')->apply($content, $object);
		Mage::helper('wordpress/shortcode_gallery')->apply($content, $object);
		Mage::helper('wordpress/shortcode_code')->apply($content, $object);
		Mage::helper('wordpress/shortcode_associatedProducts')->apply($content, $object);
		
		$contentObj = new Varien_Object(array('content' => $content));
				
		Mage::dispatchEvent('wordpress_shortcode_apply', array('content' => $contentObj, 'object' => $object, 'context' => $context, 'helper' => $this));
		
		$content = $contentObj->getContent();
	}

	/**
	  * Apply the Magento filters that are applied to static blocks
	  * This allows for {{store url=""}} & {{block type="..."}} strings
	  *
	  * @param string &$content
	  * @param array $params = array()
	  */
	protected function _addMagentoFilters(&$content)
	{
		if (strpos($content, '{{') !== false) {
			$content = Mage::helper('cms')->getBlockTemplateProcessor()->filter($content);
		}
	}

	/**
	 * Add paragraph tags to the content
	 * Taken from the WordPress core
	 * Long live open source!
	 *
	 * @param string &$content
	 */
	protected function _addParagraphsToString(&$content)
	{
		$protectedTags = array(
			'script',
			'style',
		);
		
		$safe = array();
		
		foreach($protectedTags as $tag) {
			if (strpos($content, '<' . $tag) !== false) {
				if (preg_match_all('/(<' . $tag . '.*<\/' . $tag . '>)/siU', $content, $matches)) {
					foreach($matches[1] as $match) {
						$safe[] = $match;
						$content = str_replace($match, '<!--KEY' . (count($safe)-1) . '-->', $content);
					}
				}
			}
		}
		
		$pee = $content;

		$br = true;
		$pre_tags = array();
	
		if ( trim($pee) === '' )
			return '';
	
		$pee = $pee . "\n"; // just to make things a little easier, pad the end
	
		if ( strpos($pee, '<pre') !== false ) {
			$pee_parts = explode( '</pre>', $pee );
			$last_pee = array_pop($pee_parts);
			$pee = '';
			$i = 0;
	
			foreach ( $pee_parts as $pee_part ) {
				$start = strpos($pee_part, '<pre');
	
				// Malformed html?
				if ( $start === false ) {
					$pee .= $pee_part;
					continue;
				}
	
				$name = "<pre wp-pre-tag-$i></pre>";
				$pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';
	
				$pee .= substr( $pee_part, 0, $start ) . $name;
				$i++;
			}
	
			$pee .= $last_pee;
		}
	
		$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
		// Space things out a little
		$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
		$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
		$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
		$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
		if ( strpos($pee, '<object') !== false ) {
			$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
			$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
		}
		$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
		// make paragraphs, including one at the end
		$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
		$pee = '';
		foreach ( $pees as $tinkle )
			$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
		$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
		$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
		$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
		$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
		$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
		if ( $br ) {
			$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', array($this, '_preserveNewLines'), $pee);
			$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
			$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
		}
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
		$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
		$pee = preg_replace( "|\n</p>$|", '</p>', $pee );
	
		if ( !empty($pre_tags) )
			$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);
		
		foreach(array('script', 'style') as $tag) {
			$pee = str_replace(array('<p><' . $tag, '</' . $tag . '></p>'), array('<' . $tag, '</' . $tag . '>'), $pee);
		}
		
		$pee = str_replace(array('<p>[', ']</p>'), array('[', ']'), $pee);

		$content = $pee;

		foreach($safe as $key => $value) {
			$content = str_replace('<!--KEY' . $key . '-->', $value, $content);
		}
	}

	/**
	 * Preserve new lines
	 * Used as callback in _addParagraphsToString
	 *
	 * @param array $matches
	 * @return string
	 */
	public function _preserveNewLines($matches)
	{
		return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
	}
}
