<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Sidebar_Widget_Taxonomy extends Fishpig_Wordpress_Block_Sidebar_Widget_Abstract
{
	/**
	 * Returns the current category collection
	 *
	 * @return Fishpig_Wordpress_Model_Resource_Term_Collection
	 */
	public function getTerms()
	{
		$collection = Mage::getResourceModel('wordpress/term_collection')
			->addTaxonomyFilter($this->getTaxonomy());

		$collection->getSelect()
			->reset('order')
			->order('name ASC');
			
			$collection->addParentIdFilter($this->getParentId())
				->addHasObjectsFilter();

		
		return $collection;
	}
	
	/**
	 * Returns the parent ID used to display categories
	 * If parent_id is not set, 0 will be returned and root categories displayed
	 *
	 * @return int
	 */
	public function getParentId()
	{
		return number_format($this->getData('parent_id'), 0, '', '');
	}
	
	/**
	 * Determine whether the category is the current category
	 *
	 * @param Fishpig_Wordpress_Model_Category $category
	 * @return bool
	 */
	public function isCurrentTerm($term)
	{
		if ($this->getCurrentTerm()) {
			return (int)$term->getId() === (int)$this->getCurrentTerm()->getId();
		}
		
		return false;
	}
	
	/**
	 * Retrieve the current category
	 *
	 * @return Fishpig_Wordpress_Model_Category
	 */
	public function getCurrentTerm()
	{
		return Mage::registry('wordpress_term');
	}
	
	/**
	 * Retrieve the default title
	 *
	 * @return string
	 */
	public function getDefaultTitle()
	{
		return null;
	}
	
	/**
	 * Set the posts collection
	 *
	 */
	protected function _beforeToHtml()
	{
		if (!$this->getTemplate()) {
			$this->setTemplate('wordpress/sidebar/widget/taxonomy.phtml');
		}

		return parent::_beforeToHtml();
	}
	
	/**
	 * Draw a child item
	 *
	 * @param Fishpig_Wordpress_Model_Term $term
	 * @param int $level = 0
	 * @return string
	 */
	public function drawChildItem(Fishpig_Wordpress_Model_Term $term, $level = 0)
	{
		if ($this->getRendererTemplate()) {
			$this->setTemplate($this->getRendererTemplate());
		}
		else {
			$this->setTemplate('wordpress/sidebar/widget/taxonomy/renderer.phtml');
		}

		return $this->setTerm($term)->toHtml();	
	}
	
	/**
	 * Determines whether to show the post count
	 *
	 * @return bool
	 */
	public function canShowCount()
	{
		return false;
	}
	
	/**
	 * Determines whether the taxonomy is hierarchical
	 *
	 * @return bool
	 */
	public function isHierarchical()
	{
		return true;
	}
}
