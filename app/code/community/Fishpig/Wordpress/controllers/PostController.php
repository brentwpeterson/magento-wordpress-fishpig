<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_PostController extends Fishpig_Wordpress_Controller_Abstract
{
	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * @return false|Mage_Core_Model_Abstract
	 */
	public function getEntityObject()
	{
		return $this->_initPost();
	}

	/**
	 * Display appropriate message for posted comment
	 *
	 * @return $this
	 */
	public function preDispatch()
	{
		parent::preDispatch();
		
		$this->_handlePostedComment();
		
		$post = $this->getEntityObject();
		
		if ($post->isType('page') && (int)$post->getId() === (int)Mage::helper('wordpress/router')->getBlogPageId()) {
			return $this->_forward('index', 'index', 'wordpress');
		}

		return $this;
	}

	/**
	 * Display the post view page
	 *
	 */
	public function viewAction()
	{
		$post = $this->getEntityObject();

		$layoutHandles = array(
			'wordpress_post_view',
			'wordpress_' . $post->getPostType() . '_view',
			'wordpress_' . $post->getPostType() . '_view_' . $post->getId(),
			'wordpress_post_view_' . strtoupper($post->getPostType()),
		);
		
		if ($post->getTypeInstance()->isHierarchical()) {
			$buffer = $post->getParentPost();
	
			while ($buffer) {
				$layoutHandles[] = 'wordpress_' . $post->getPostType() . '_view_parent_' . $buffer->getId();
				
				// Legacy
				if ($post->isType('page')) {
					$layoutHandles[] = 'wordpress_' . $post->getPostType() . '_parent_' . $buffer->getId();
				}

				$buffer = $buffer->getParentPost();
			}
		}

		$this->_addCustomLayoutHandles($layoutHandles);
		$this->_initLayout();
		$this->_title(strip_tags($post->getPostTitle()));
		
		if (($headBlock = $this->getLayout()->getBlock('head')) !== false) {
			$headBlock->addItem(
				'link_rel', 
				$post->getCommentFeedUrl(), 
				sprintf('rel="alternate" type="application/rss+xml" title="%s &raquo; %s Comments Feed"', 
					Mage::helper('wordpress')->getWpOption('blogname'), 
					$post->getPostTitle()
				)
			);

			if (Mage::helper('wordpress')->getWpOption('default_ping_status') === 'open' && $post->getPingStatus() == 'open') {
				$headBlock->addItem('link_rel', Mage::helper('wordpress')->getBaseUrl() . 'xmlrpc.php', 'rel="pingback"');				
			}
		}

		if ($post->isType('page') && (int)$post->getId() === (int)Mage::helper('wordpress/router')->getHomepagePageId()) {
			$post->setCanonicalUrl(Mage::helper('wordpress')->getUrl());

			if (Mage::helper('wordpress')->getBlogRoute() === '') {
				$this->_crumbs = array();
			}
			else {
				array_pop($this->_crumbs);
			}
		}
		else if ($post->getTypeInstance()->isHierarchical()) {
			$posts = array();
			$buffer = $post;
	
			while ($buffer) {
				$this->_title(strip_tags($buffer->getPostTitle()));
				$posts[] = $buffer;
				$buffer = $buffer->getParentPost();
			}

			$posts = array_reverse($posts);
			
			// Remove current post from end array
			array_pop($posts);
			
			foreach($posts as $buffer) {
				$this->addCrumb('page_' . $buffer->getId(), array('label' => $buffer->getPostTitle(), 'link' => $buffer->getUrl()));
			}
		}
		else if ($post->hasParentCategory()) {
			$categories = array();
			$category = $post->getParentCategory();

			while($category) {
				array_unshift($categories, $category);
				$category = $category->getParentTerm();
			}
			
			foreach($categories as $category) {
				$this->addCrumb('post_category_' . $category->getId(), array('label' => $category->getName(), 'link' => $category->getUrl()));
			}
		}
		
		$this->addCrumb('post', array('label' => $post->getPostTitle()));

		$this->renderLayout();
	}

	/**
	 * Display the appropriate message for a posted comment
	 *
	 * @return $this
	 */
	protected function _handlePostedComment()
	{
		$commentId = $this->getRequest()->getParam('comment');
		
		if ($commentId && $this->getRequest()->getActionName() === 'view') {
			$comment = Mage::getModel('wordpress/post_comment')->load($commentId);
			
			if ($comment->getId() && $comment->getPost()->getId() === $this->getEntityObject()->getId()) {
				if ($comment->isApproved()) {
					header('Location: ' . $comment->getUrl());
					exit;
				}
				else {
					Mage::getSingleton('core/session')->addSuccess($this->__('Your comment is awaiting moderation.'));	
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Initialise the post model
	 * Provides redirects for Guid links when using permalinks
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	protected function _initPost()
	{
		if (($post = Mage::registry('wordpress_post')) !== null) {
			$previewId = $this->getRequest()->getParam('preview_id');

			if ($previewId === $post->getId()) {
				$posts = Mage::getResourceModel('wordpress/post_collection')
					->addFieldToFilter('post_parent', $post->getId())
					->addPostTypeFilter('revision')
					->setPageSize(1)
					->setOrder('post_modified', 'desc')
					->load();
				
				if (count($posts) > 0) {
					$post = $posts->getFirstItem();
					
					Mage::unregister('wordpress_post');
					Mage::register('wordpress_post', $post);
					
					return $post;	
				}
			}
			
			return $post;
		}

		$isPreview = $this->getRequest()->getParam('preview', false);

		if ($postId = $this->getRequest()->getParam('p')) {
			$post = Mage::getModel('wordpress/post')->load($postId);

			if ($post->getId()) {
				if ($isPreview || Mage::helper('wordpress/post')->useGuidLinks()) {
					Mage::register('wordpress_post', $post);

					return $post;
				}

				if ($post->canBeViewed()) {
					$this->_redirectUrl($post->getUrl());
					$this->getResponse()->sendHeaders();
					exit;
				}
			}
		}
		else if ($postId = $this->getRequest()->getParam('id')) {
			$post = Mage::getModel('wordpress/post')
				->setPostType($this->getRequest()->getParam('post_type', '*'))
				->load($postId);
			
			if ($post->getId() && ($post->canBeViewed() || $isPreview)) {
				Mage::register('wordpress_post', $post);
				
				return $post;
			}
		}

		return false;
	}

	/**
	 * Display the comments feed
	 *
	 * @return void
	 */	
	public function feedAction()
	{
		return $this->commentsFeedAction();
	}
}
