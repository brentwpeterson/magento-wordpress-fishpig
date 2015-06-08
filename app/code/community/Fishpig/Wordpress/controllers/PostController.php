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
		
		return $this;
	}

	/**
	 * Display the post view page
	 *
	 */
	public function viewAction()
	{
		$post = Mage::registry('wordpress_post');
		
		$this->_rootTemplates[] = 'post_view';

		$this->_addCustomLayoutHandles(array(
			'wordpress_post_view',
			'wordpress_post_view_' . strtoupper($post->getPostType()),
			'wordpress_post_view_' . $post->getId(),
		));
		
		$this->_initLayout();

		$this->_title(strip_tags($post->getPostTitle()));
		
		if (($headBlock = $this->getLayout()->getBlock('head')) !== false) {
			$feedTitle = sprintf('%s %s %s Comments Feed', Mage::helper('wordpress')->getWpOption('blogname'), '&raquo;', $post->getPostTitle());

			$headBlock->addItem('link_rel', 
				$post->getCommentFeedUrl(), 
				'rel="alternate" type="application/rss+xml" title="' . $feedTitle . '"'
			);

			$headBlock->setDescription($post->getMetaDescription());
			
			$canPing = Mage::helper('wordpress')->getWpOption('default_ping_status') === 'open';

			if ($canPing && $post->getPingStatus() == 'open') {
				$headBlock->addItem('link_rel', Mage::helper('wordpress')->getBaseUrl() . 'xmlrpc.php', 'rel="pingback"');				
			}
		}
			
		if ($post->hasParentCategory()) {
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

		$isPreview = $this->getRequest()->getParam('preview', false);;

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
				->setPostType($this->getRequest()->getParam('post_type', 'post'))
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
