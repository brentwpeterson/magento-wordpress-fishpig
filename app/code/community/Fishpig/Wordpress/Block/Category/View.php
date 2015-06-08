<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Category_View extends Fishpig_Wordpress_Block_Post_List_Wrapper_Abstract
{
	/**
	 * Returns the current Wordpress category
	 *
	 * @return Fishpig_Wordpress_Model_Post_Category
	 */
	public function getCategory()
	{
		return $this->_getData('category')
			? $this->_getData('category')
			: Mage::registry('wordpress_category');
	}

	/**
	 * Returns the current Wordpress category
	 *
	 * @return Fishpig_Wordpress_Model_Post_Category
	 */
	public function getCurrentCategory()
	{
		return $this->getCategory();
	}
	
	/**
	 * Generates and returns the collection of posts
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Collection
	 */
	protected function _getPostCollection()
	{
		if ($this->getCategory()) {
			return $this->getCategory()->getPostCollection();
		}
		
		return false;
	}
}
