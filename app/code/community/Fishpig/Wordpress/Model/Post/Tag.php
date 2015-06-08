<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Post_Tag extends Fishpig_Wordpress_Model_Term
{
	public function _construct()
	{
		$this->_init('wordpress/post_tag');
	}
	
	/**
	 * Retrieve the taxonomy type
	 *
	 * @return string
	 */
	public function getTaxonomy()
	{
		return 'post_tag';
	}
	
	/**
	 * Loads a category model based on a post ID
	 * 
	 * @param int $postId
	 */
	public function loadByPostId($postId)
	{
		return $this->load($postId, 'object_id');
	}
	
	/**
	 * Loads the posts belonging to this category
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Collection
	 */    
    public function getPostCollection()
    {
    	if (!$this->hasPostCollection()) {
    		$this->setPostCollection(
    			parent::getPostCollection()->addTagIdFilter($this->getId())
    		);
    	}
    	
    	return $this->_getData('post_collection');
    }

	/**
	 * Retrieve the URI for this term
	 * This takes into account parent relationships
	 * This does not include the base URL
	 *
	 * @return string
	 */
	public function getUri()
	{
		if (!$this->hasUri()) {
			$this->setUri($this->getSlug());
		}
		
		return $this->_getData('uri');
	}
	
	/*
	 * Retrieve the tag base
	 *
	 * @return string
	 */
	public function getUriPrefix()
	{
		return ($base = trim(Mage::helper('wordpress')->getWpOption('tag_base', 'tag'), '/')) === ''
			? 'tag'
			: $base;
	}
}
