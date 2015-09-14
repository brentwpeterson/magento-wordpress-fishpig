<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Social extends Fishpig_Wordpress_Helper_Abstract
{
	const SERVICE_SHARETHIS = 'sharethis';
		
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag('wordpress/social/enabled');
	}
	
	public function getService()
	{
		if (!$this->isEnabled()) {
			return false;
		}
		
		$service = trim(Mage::getStoreConfig('wordpress/social/service'));
		
		return $service !== ''
			? $service
			: false;
	}
	
	public function isShareThis()
	{
		return $this->getService() === self::SERVICE_SHARETHIS;
	}
	
	public function addCodeToHead()
	{
		if ($this->isShareThis()) {
			$_layout = Mage::getSingleton('core/layout');
			
			$_layout->getBlock('head')->append(
				$_layout->createBlock('core/text')
					->setText(
						$this->_getHeadHtml()
					)
			);
		}
		
		return $this;
	}
	
	public function getButtons(Fishpig_Wordpress_Model_Post $post)
	{
		if ($this->isShareThis()) {
			$buttonsHtml = $this->_getButtonsHtml();
			
			if (preg_match_all('/(<span.*)(>.*<\/span>)/Us', $buttonsHtml, $matches)) {
				foreach($matches[1] as $it => $prefix) {
					$suffix = $matches[2][$it];

					$middle = " st_title='" . addslashes(strip_tags($post->getPostTitle())) . "'";
					$middle .= " st_url='" . $post->getPermalink() . "'";
					
					if ($featuredImage = $post->getFeaturedImage()) {
						$middle .= " st_image='" . $featuredImage->getAvailableImage() . "'";
					}

					$middle .= " st_summary='" . ($post->getData('post_excerpt') ? addslashes(strip_tags($post->getData('post_excerpt'))) : '') . "'";

					$buttonsHtml = str_replace($matches[0][$it], $prefix . $middle . $suffix, $buttonsHtml);
				}
				
				return $buttonsHtml;
			}
		}

		return '';
	}
	
	protected function _getHeadHtml()
	{
		return Mage::getStoreConfig('wordpress/social/head_html');
	}
	
	protected function _getButtonsHtml()
	{
		return Mage::getStoreConfig('wordpress/social/buttons_html');
	}
}
