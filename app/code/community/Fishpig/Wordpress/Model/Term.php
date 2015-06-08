<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
 
class Fishpig_Wordpress_Model_Term extends Fishpig_Wordpress_Model_Abstract
{
	/**
	 * Event data
	 *
	 * @var string
	 */
	protected $_eventPrefix = 'wordpress_term';
	protected $_eventObject = 'term';
	
	public function _construct()
	{
		$this->_init('wordpress/term');
	}
	
	/**
	 * Retrieve an array of the default WP taxonomies
	 *
	 * @return array
	 */
	public function getDefaultTermTaxonomyTypes()
	{
		return array('category', 'link_category', 'post_tag');
	}
	
	/**
	 * Determine whether this term is a custom term or a default term
	 *
	 * @return bool
	 */
	public function isDefaultTerm()
	{
		return in_array($this->_getData('taxonomy'), $this->getDefaultTermTaxonomyTypes());
	}
	
	/**
	 * Retrieve the taxonomy label
	 *
	 * @return string
	 */
	public function getTaxonomyLabel()
	{
		if ($this->getTaxonomy()) {
			return ucwords(str_replace('_', ' ', $this->getTaxonomy()));
		}
		
		return false;
	}
	
	/**
	 * Retrieve the parent term
	 *
	 * @reurn false|Fishpig_Wordpress_Model_Term
	 */
	public function getParentTerm()
	{
		if (!$this->hasParentTerm()) {
			$this->setParentTerm(false);
			
			if ($this->getParentId()) {
				$parentTerm = Mage::getModel($this->getResourceName())->load($this->getParentId());
				
				if ($parentTerm->getId()) {
					$this->setParentTerm($parentTerm);
				}
			}
		}
		
		return $this->_getData('parent_term');
	}
	
	/**
	 * Retrieve a collection of children terms
	 *
	 * @return Fishpig_Wordpress_Model_Mysql_Term_Collection
	 */
	public function getChildrenTerms()
	{
		if (!$this->hasChildrenTerms()) {
			$this->setChildrenTerms($this->getCollection()->addParentFilter($this));
		}
		
		return $this->_getData('children_terms');
	}
	
	/**
	 * Loads the posts belonging to this category
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Collection
	 */    
    public function getPostCollection()
    {
		if (!$this->hasPostCollection()) {
			if ($this->getTaxonomy()) {
				$posts = $this->_getObjectResourceModel()
    				->addIsViewableFilter()
    				->addTermIdFilter($this->getId(), $this->getTaxonomy());
    			
	    		$this->setPosts($posts);
	    	}
    	}
    	
    	return $this->_getData('posts');
    }
  
	/**
	 * Retrieve the object resource model
	 *
	 * @return Fishpig_Wordpress_Model_Resource_Post_Collection_Abstract
	 */    
    protected function _getObjectResourceModel()
    {
    	return parent::getPostCollection();
    }
      
	/**
	 * Retrieve the numbers of items that belong to this term
	 *
	 * @return int
	 */
	public function getItemCount()
	{
		return $this->getCount();
	}

	/**
	 * Load a term based on it's slug
	 *
	 * @param string $slug
	 * @return $this
	 */	
	public function loadBySlug($slug)
	{
		return $this->load($slug, 'slug');
	}
	
	/**
	 * Retrieve the parent ID
	 *
	 * @return int|false
	 */	
	public function getParentId()
	{
		return $this->_getData('parent')
			? $this->_getData('parent')
			: false;
	}
	
	/**
	 * Retrieve the taxonomy type for this term
	 *
	 * @return string
	 */
	public function getTaxonomyType()
	{
		return $this->getTaxonomy();
	}
	
	/**
	 * Retrieve the URL for this term
	 *
	 * @return string
	 */
	public function getUrl()
	{
		if (!$this->hasUrl()) {
			$this->setUrl(Mage::helper('wordpress')->getUrl($this->getUriPrefix() . '/' . $this->getUri() . '/'));
		}
		
		return $this->_getData('url');
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
			$this->setUri($this->getResource()->getTermUri($this));
		}
		
		return $this->_getData('uri');
	}
	
	/**
	 * Retrieve all of the URI's for this taxonomy type
	 *
	 * @return string
	 */
	public function getAllUris()
	{
		return $this->getResource()->getUrisByTaxonomy($this->getTaxonomyType());
	}
	
	/**
	 * Retrieve the 	URI prefix for all URL's
	 *
	 * @return string
	 */
	public function getUriPrefix()
	{
		return $this->getTaxonomyType();
	}
	
	/**
	 * Determine whether $post is in the current term
	 *
	 * @param int|Fishpig_Wordpress_Model_Post_Abstract $post
	 * @return bool
	 */
	public function containsPost($post)
	{
		return $this->getResource()->containsPost($this, $post);
	}
}
