<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Plugin_ShareThis extends Fishpig_Wordpress_Helper_Plugin_Abstract
{
	/**
	 * Determine whether the plugin has been enabled in the WordPress Admin
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		Mage::helper('wordpress')->log(get_class($this) . ' is deprecated and will be removed shortly. Remove template references to it before upgrading');
		
		return false;
	}

	/**
	 * Determine whether to display icons on post page
	 * This is set in the ShareThis configuration in the WordPress Admin
	 *
	 * @return bool
	 */
	public function canDisplayOnPost()
	{
		return $this->isEnabled() && Mage::helper('wordpress')->getWpOption('st_add_to_content') == 'yes' ? true : false;
	}
	
	/**
	 * Retrieve the Javascript include HTML
	 *
	 * @return string
	 */
	public function getJavascriptHtml()
	{
		if ($this->isEnabled() && !$this->hasJavascriptAlreadyIncluded()) {
			$this->setJavascriptAlreadyIncluded(true);
			return Mage::helper('wordpress')->getWpOption('st_widget');
		}
	}
	
	/**
	 * Retrieve the icon HTML for the post
	 *
	 * @param Fishpig_Wordpress_Model_Post $post
	 * @return string
	 */
	public function getIcons(Fishpig_Wordpress_Model_Post $post)
	{
		if ($this->isEnabled()) {
			$html = Mage::helper('wordpress')->getWpOption('st_tags');
			
			if (preg_match_all("/(<span.*><\/span>)/iU", $html, $matches)) {
				$tags = array();

				foreach($matches[1] as $match) {
					$class = $this->_patternMatch("/class='(.*)'/iU", $match);
					$displayText = $this->_patternMatch("/displayText='(.*)'/iU", $match);
					$stVia = trim($this->_patternMatch("/st_via='(.*)'/iU", $match));
					
					if ($displayText) {
						$displayText = ' displayText="' . $displayText . '" ';
					}
					
					if ($stVia) {
						$stVia = ' st_via="' . $stVia . '"';
					}

					$tag = sprintf('<span class="%s"%sst_title="%s" st_summary="%s" st_url="%s"%s></span>', 
									$class, $displayText, addslashes($post->getPostTitle()), trim(strip_tags(addslashes($post->getPostExcerpt()))), $post->getPermalink(), $stVia);
				
					if ($image = $post->getFeaturedImage()) {
						$tag = str_replace('></span>', ' st_image="' . $image->getAvailableImage() . '"></span>', $tag);
					}
					
					$tags[] = $tag;
				}

				return implode('', $tags);
			}
		}
	}
	
	/**
	 * Match a pattern
	 *
	 * @param string $pattern
	 * @param string $string
	 * @param int $rturn
	 * @return false|string
	 */
	protected function _patternMatch($pattern, $string, $return = 1)
	{
		if (preg_match($pattern, $string, $match)) {
			if (isset($match[$return])) {
				return $match[$return];
			}
			
			return $match;
		}
		
		return false;	
	}
}
