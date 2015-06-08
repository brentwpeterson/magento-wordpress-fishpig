<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

abstract class Fishpig_Wordpress_Model_Post_Abstract extends Fishpig_Wordpress_Model_Abstract
{
	/**
	 * Entity meta infromation
	 *
	 * @var string
	 */
	protected $_metaTable = 'wordpress/post_meta';	
	protected $_metaTableObjectField = 'post_id';
	
	static protected $_types = null;
	
	static public function getType($type = null)
	{
		if (is_null(self::$_types)) {
			self::$_types = json_decode(json_encode((array)Mage::getConfig()->getNode('wordpress/post/types')), true);
		}
		
		if (!$type) {
			return self::$_types;
		}
		
		return isset(self::$_types[$type])
			? self::$_types[$type]
			: false;
	}
	
	static public function typeExists($type)
	{
		$types = self::getType();
		
		return isset($types[$type]);
	}


	/**
	 * Inject string 'Protected: ' on password protected posts
	 *
	 * @return string
	 */
	public function getPostTitle()
	{
		if ($this->getPostPassword() !== '') {
			return Mage::helper('wordpress')->__('Protected: %s', $this->_getData('post_title'));
		}
	
		return $this->_getData('post_title');
	}
	
	/**
	 * Retrieve the URL for the comments feed
	 *
	 * @return string
	 */
	public function getCommentFeedUrl()
	{
		return rtrim($this->getPermalink(), '/') . '/feed/';
	}
	 
	/**
	 * Gets the post content
	 *
	 * @return string
	 */
	public function getPostContent($context = 'full')
	{
		$key = rtrim('filtered_post_content_' . $context, '_');
		
		if (!$this->hasData($key)) {
			$this->setData($key, Mage::helper('wordpress/filter')->applyFilters($this->_getData('post_content'), $this, $context));
		}
		
		return $this->_getData($key);
	}

	/**
	 * Returns a collection of comments for this post
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Comment_Collection
	 */
	public function getComments()
	{
		if (!$this->hasData('comments')) {
			$this->setData('comments', $this->getResource()->getPostComments($this));
		}
		
		return $this->getData('comments');
	}

	/**
	 * Returns a collection of images for this post
	 * 
	 * @return Fishpig_Wordpress_Model_Mysql4_Image_Collection
	 *
	 * NB. This function has not been thoroughly tested
	 *        Please report any bugs
	 */
	public function getImages()
	{
		if (!$this->hasData('images')) {
			$this->setImages(Mage::getResourceModel('wordpress/image_collection')->setParent($this->getData('ID')));
		}
		
		return $this->getData('images');
	}

	/**
	 * Returns the featured image for the post
	 *
	 * This image must be uploaded and assigned in the WP Admin
	 *
	 * @return Fishpig_Wordpress_Model_Image
	 */
	public function getFeaturedImage()
	{
		if (!$this->hasData('featured_image')) {
			$this->setFeaturedImage($this->getResource()->getFeaturedImage($this));
		}
	
		return $this->getData('featured_image');	
	}
	
	/**
	 * Get the model for the author of this post
	 *
	 * @return Fishpig_Wordpress_Model_Author
	 */
	public function getAuthor()
	{
		return Mage::getModel('wordpress/user')->load($this->getAuthorId());	
	}
	
	/**
	 * Returns the author ID of the current post
	 *
	 * @return int
	 */
	public function getAuthorId()
	{
		return $this->getData('post_author');
	}
	
	/**
	 * Returns the post date formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostDate($format = null)
	{
		if (($date = $this->getData('post_date_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}
	
	/**
	 * Returns the post date formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostModifiedDate($format = null)
	{
		if (($date = $this->getData('post_modified_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}
	
	/**
	 * Returns the post time formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostTime($format = null)
	{
		if (($date = $this->getData('post_date_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}

	/**
	 * Retrieve the META description for a Post
	 *
	 * @return string
	 */
	public function getMetaDescription()
	{
		if (!$this->hasMetaDescription()) {
			$this->setMetaDescription(false);

			if (($desc = trim($this->getPostExcerpt(false))) !== '') {
				$desc = preg_replace('/<script(.*)>[^<]{1,}<\/script>/iU', '', $desc);
				$desc = preg_replace("/[\n\r\t]{1,}/", '', $desc);
		
				$this->setMetaDescription(strip_tags($desc));
			}
		}
		
		return $this->_getData('meta_description');
	}

	/**
	 * Determine whether the post has been published
	 *
	 * @return bool
	 */
	public function isPublished()
	{
		return $this->getPostStatus() == 'publish';
	}

	/**
	 * Determine whether the post has been published
	 *
	 * @return bool
	 */
	public function isPending()
	{
		return $this->getPostStatus() == 'pending';
	}

	
	/**
	 * Retrieve the preview URL
	 *
	 * @return string
	 */
	public function getPreviewUrl()
	{
		if ($this->isPending()) {
			return Mage::helper('wordpress')->getUrl('?p=' . $this->getId() . '&preview=1');
		}
		
		return '';
	}
	
	/**
	 * Determine whether the current user can view the post/page
	 * If visibility is protected and user has supplied wrong password, return false
	 *
	 * @return bool
	 */
	public function isViewableForVisitor()
	{
		return $this->getPostPassword() === '' 
			|| Mage::getSingleton('wordpress/session')->getPostPassword() == $this->getPostPassword(); 
	}
	
	/**
	 * Retrieve the object's post type
	 *
	 * @return string
	 */
	public function getPostType()
	{
		if (!$this->_getData('post_type')) {
			$this->setData('post_type', $this->_getDefaultPostType());
		}
		
		return $this->_getData('post_type');
	}
	
	/**
	 * Retrieve the object's default post type
	 *
	 * @return string
	 */
	protected function _getDefaultPostType()
	{
		return 'post';
	}
	
	/**
	 * Determine whether the post is a sticky post
	 * This only works if the post collection has been loaded with addStickyPostsToCollection
	 *
	 * @return bool
	 */	
	public function isSticky()
	{
		return $this->_getData('is_sticky');
	}
	
	/**
	 * Determine whether a post object can be viewed
	 *
	 * @return string
	 */
	public function canBeViewed()
	{
		return $this->isPublished()
			|| ($this->getPostStatus() === 'private' && Mage::getSingleton('customer/session')->isLoggedIn());
	}
}
