<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Page_View_Comment_Pager extends Fishpig_Wordpress_Block_Post_View_Comment_Pager 
{
	/**
	 * Retrieve the page object
	 * This is overridden to save duplicating all post comment code
	 *
	 * @return Fishpig_Wordpress_Model_Page
	 */
	public function getPost()
	{
		return $this->getPage();
	}

	/**
	 * Retrieve the page object
	 *
	 * @return Fishpig_Wordpress_Model_Page
	 */	
	public function getPage()
	{
		return $this->_getData('page')
			? $this->_getData('page')
			: Mage::registry('wordpress_page');
	}
}
