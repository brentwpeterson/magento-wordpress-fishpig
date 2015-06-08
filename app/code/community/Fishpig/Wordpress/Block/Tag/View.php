<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Tag_View extends Fishpig_Wordpress_Block_Post_List_Wrapper_Abstract
{
/**
	 * Caches and returns the current category
	 *
	 * @return Fishpig_Wordpress_Model_Post_Categpry
	 */
	public function getTag()
	{
		if (!$this->hasData('tag')) {
			if ($categoryId = $this->getData('tag_id')) {
				$category = Mage::getModel('wordpress/post_tag')->load($categoryId);
				
				if ($category->getId() == $categoryId) {
					$this->setData('tag', $category);
				}
			}
			else {
				$this->setData('tag', Mage::registry('wordpress_post_tag'));
			}
		}
		
		return $this->getData('tag');
	}

	/**
	 * Returns the current tag's ID
	 *
	 * @return int
	 */
	public function getTagId()
	{
		if ($tag = $this->getTag()) {
			return $tag->getId();
		}
	}
	
	/**
	 * Generates and returns the collection of posts
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Collection
	 */
	protected function _getPostCollection()
	{
		if (is_null($this->_postCollection)) {
			$this->_postCollection = parent::_getPostCollection()
				->addTagIdFilter($this->getTagId());
		}
	
		return $this->_postCollection;
	}
}
