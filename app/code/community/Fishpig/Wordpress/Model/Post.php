<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
 
class Fishpig_Wordpress_Model_Post extends Fishpig_Wordpress_Model_Post_Abstract
{
	/**
	 *
	 */
	public $ID;

	/**
	 * Event data
	 *
	 * @var string
	*/
	protected $_eventPrefix = 'wordpress_post';
	protected $_eventObject = 'post';
	
	/**
	 * Set the model's resource
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('wordpress/post');
	}

	/**
	 * Set the categories after loading
	 *
	 * @return $this
	 */
	protected function _afterLoad()
	{
		parent::_afterLoad();

		$this->getResource()->preparePosts(array($this));
		
		return $this;
	}

	/**
	 * Returns the permalink used to access this post
	 *
	 * @return string
	 */
	public function getPermalink()
	{
		return $this->getUrl();
	}

	public function getPostFormat()
	{
		if (!$this->hasPostFormat()) {
			$this->setPostFormat(false);
			
			$formats = Mage::getResourceModel('wordpress/term_collection')
				->addTaxonomyFilter('post_format')
				->setPageSize(1)
				->load();
			
			if (count($formats) > 0) {
				$this->setPostFormat(
					str_replace('post-format-', '', $formats->getFirstItem()->getSlug())
				);
			}
		}
		
		return $this->_getData('post_format');
	}

	/**
	 * Wrapper for self::getPermalink()
	 *
	 * @return string
	 */
	public function getUrl()
	{
		if (!$this->hasUrl()) {
			if ($this->hasPermalink()) {
				$this->setUrl(Mage::helper('wordpress')->getUrl($this->_getData('permalink')));
			}
			else {
				$this->setUrl($this->getGuid());
			}
		}
		
		return $this->_getData('url');
	}

	/**
	 * Retrieve the post GUID
	 *
	 * @return string
	 */	
	public function getGuid()
	{
		return Mage::helper('wordpress')->getUrl() . '?p=' . $this->getId();
	}

	/**
	 * Retrieve the post excerpt
	 * If no excerpt, try to shorten the post_content field
	 *
	 * @return string
	 */
	public function getPostExcerpt($includeSuffix = true)
	{
		if (!$this->getData('post_excerpt')) {
			$excerpt = $this->hasMoreTag()
				? $this->_getPostTeaser($includeSuffix)
				: $this->getPostContent('excerpt');
				
			$this->setPostExcerpt($excerpt);
		}			

		return $this->getData('post_excerpt');
	}
	
	/**
	 * Determine twhether the post has a more tag in it's content field
	 *
	 * @return bool
	 */
	public function hasMoreTag()
	{
		return strpos($this->getData('post_content'), '<!--more') !== false;
	}
	
	/**
	 * Retrieve the post teaser
	 * This is the data from the post_content field upto to the MORE_TAG
	 *
	 * @return string
	 */
	protected function _getPostTeaser($includeSuffix = true)
	{
		if ($this->hasMoreTag()) {
			$content = $this->getPostContent('excerpt');

			if (preg_match('/<!--more (.*)-->/', $content, $matches)) {
				$anchor = $matches[1];
				$split = $matches[0];
			}
			else {
				$split = '<!--more-->';
				$anchor = $this->_getTeaserAnchor();
			}
			
			$excerpt = trim(substr($content, 0, strpos($content, $split)));

			if ($excerpt !== '' && $includeSuffix && $anchor) {
				$excerpt .= sprintf(' <a href="%s" class="read-more">%s</a>', $this->getPermalink(), $anchor);
			}
			
			return $excerpt;
		}
		
		return null;
	}
	
	/**
	 * Retrieve a collection of all parent categories
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Category_Collection
	 */
	public function getParentCategories()
	{
		if (!$this->hasData('parent_categories')) {
			$this->setParentCategories($this->getResource()->getParentCategories($this));
		}
		
		return $this->_getData('parent_categories');
	}

	/**
	 * Gets a collection of post tags
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Tag_Collection
	 */
	public function getTags()
	{
		if (!$this->hasData('tags')) {
			$this->setTags($this->getResource()->getPostTags($this));
		}
		
		return $this->_getData('tags');
	}

	/**
	 * Retrieve the read more anchor text
	 *
	 * @return string|false
	 */
	protected function _getTeaserAnchor()
	{
		// Allows translation
		return stripslashes(Mage::helper('wordpress')->__('Continue reading <span class=\"meta-nav\">&rarr;</span>'));
	}
	
	/**
	 * Retrieve the previous post
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	public function getPreviousPost()
	{
		if (!$this->hasPreviousPost()) {
			$this->setPreviousPost(false);
			
			$collection = Mage::getResourceModel('wordpress/post_collection')
				->addIsViewableFilter()
				->addPostTypeFilter($this->getPostType())
				->addPostDateFilter(array('lt' => $this->_getData('post_date')))
				->setPageSize(1)
				->setCurPage(1)
				->setOrderByPostDate()
				->load();

			if ($collection->count() > 0) {
				$this->setPreviousPost($collection->getFirstItem());
			}
		}
		
		return $this->_getData('previous_post');
	}
	
	/**
	 * Retrieve the next post
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	public function getNextPost()
	{
		if (!$this->hasNextPost()) {
			$this->setNextPost(false);
			
			$collection = Mage::getResourceModel('wordpress/post_collection')
				->addIsViewableFilter()
				->addPostTypeFilter($this->getPostType())
				->addPostDateFilter(array('gt' => $this->_getData('post_date')))
				->setPageSize(1)
				->setCurPage(1)
				->setOrderByPostDate('asc')
				->load();

			if ($collection->count() > 0) {
				$this->setNextPost($collection->getFirstItem());
			}
		}
		
		return $this->_getData('next_post');
	}
	
	/**
	 * Get a collection of terms by the taxonomy
	 *
	 * @param string $taxonomy
	 * @return Fishpig_Wordpress_Model_Resource_Term_Collection
	 */
	public function getTaxonomyCollection($taxonomy)
	{
		return Mage::getResourceModel('wordpress/term_collection')
			->addTaxonomyFilter($taxonomy)
			->addPostIdFilter($this->getId());
	}
}
