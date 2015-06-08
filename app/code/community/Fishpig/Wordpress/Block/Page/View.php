<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Page_View extends Fishpig_Wordpress_Block_Abstract
{
	/**
	 * Returns the currently loaded page model
	 *
	 * @return Fishpig_Wordpress_Model_Page
	 */
	public function getPage()
	{
		return $this->_getData('page')
			? $this->_getData('page')
			: Mage::registry('wordpress_page');
	}

	
	/**
	  * Returns the HTML for the comments block
	  *
	  * @return string
	  */
	public function getCommentsHtml()
	{
		return $this->getChildHtml('comments');
	}

	/**
	 * Setup the comments block
	 *
	 */
	protected function _beforeToHtml()
	{
		if (($commentsBlock = $this->getChild('comments')) !== false) {
			$commentsBlock->setPost($this->getPage());
		}
		
		return parent::_beforeToHtml();
	}
	
	/**
	 * Retrieve the HTML for the password protect form
	 *
	 * @return string
	 */
	public function getPasswordProtectHtml()
	{
		if (!$this->hasPasswordProtectHtml()) {
			$block = $this->getLayout()
				->createBlock('wordpress/template')
				->setTemplate('wordpress/protected.phtml')
				->setEntityType('page')
				->setPost($this->getPage())
				->setPage($this->getPage());
					
			$this->setPasswordProtectHtml($block->toHtml());
		}
		
		return $this->_getData('password_protect_html');
	}
}
