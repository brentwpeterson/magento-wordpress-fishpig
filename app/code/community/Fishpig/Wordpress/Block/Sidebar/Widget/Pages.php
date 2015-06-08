<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Sidebar_Widget_Pages extends Fishpig_Wordpress_Block_Sidebar_Widget_Abstract
{
	/**
	 * Returns the currently loaded page model
	 *
	 * @return Fishpig_Wordpress_Model_Page
	 */
	public function getPage()
	{
		return Mage::registry('wordpress_page');
	}
	
	/**
	 * Retrieve a collection  of pages
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Page_Collection
	 */
	public function getPages()
	{
		$pages = Mage::getResourceModel('wordpress/page_collection');

		if ($this->hasParentId()) {
			$pages->addPostParentIdFilter($this->getParentId());
		}
		else if ($this->getPage() && $this->getPage()->hasChildren()) {
			$pages->addPostParentIdFilter($this->getPage()->getId());
		}
		else {
			$pages->addPostParentIdFilter(0);
		}

		$pages->addIsViewableFilter();
		$pages->orderByMenuOrder();
		$pages->setOrderByPostDate();
		$pages->load();

		return $pages;
	}
	
	/**
	 * Retrieve the block title
	 *
	 * @return string
	 */
	public function getTitle()
	{
		if ($this->getPage() && $this->getPage()->hasChildren()) {
			return $this->getPage()->getPostTitle();
		}
		
		return parent::getTitle();
	}
	
	/**
	 * Retrieve the default title
	 *
	 * @return string
	 */
	public function getDefaultTitle()
	{
		return $this->__('Pages');
	}
}
