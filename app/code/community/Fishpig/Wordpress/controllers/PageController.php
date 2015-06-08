<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_PageController extends Fishpig_Wordpress_Controller_Abstract
{
	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * @return false|Fishpig_Wordpress_Model_Page
	 */
	public function getEntityObject()
	{
		return $this->_initPage();
	}
		
	/**
	 * If the feed parameter is set, forward to the comments action
	 *
	 * @return $this
	 */
	public function preDispatch()
	{
		parent::preDispatch();

		$this->_handlePostedComment();

		if ($this->_initPage()->isBlogPage()) {
			$this->_forceForwardViaException('index', 'index');
			return false;
		}

		return $this;	
	}

	/**
	 * Display the post view page
	 *
	 */
	public function viewAction()
	{
		$page = $this->_initPage();
		$layoutHandles = array(
			'wordpress_page_view',
			'wordpress_page_view_' . $page->getId(),
		);

		$buffer = $page->getParentPage();
		
		while ($buffer) {
			$layoutHandles[] = 'wordpress_page_parent_' . $buffer->getId();
			$buffer = $buffer->getParentPage();
		}
		
		$this->_addCustomLayoutHandles($layoutHandles);
		
		$this->_setPageViewTemplate();
		
		$this->_initLayout();		

		if (($headBlock = $this->getLayout()->getBlock('head')) !== false) {
			$headBlock->setDescription($page->getMetaDescription());
		}

		$homepageId = Mage::helper('wordpress/router')->getHomepagePageId();

		if ($page->getId() === $homepageId) {
			$this->_rootTemplates[] = 'homepage';

			$page->setCanonicalUrl(
				Mage::helper('wordpress')->getUrl()
			);

			if (Mage::helper('wordpress')->getBlogRoute() === '') {
				$this->_crumbs = array();
			}
			else {
				array_pop($this->_crumbs);

				$this->addCrumb('wp_page', array(
					'label' => $page->getPostTitle(),
					'title' => $page->getPostTitle(),
				));
			}
		}
		else {
			$pages = array();
			$buffer = $page;
	
			while ($buffer) {
				$this->_title(strip_tags($buffer->getPostTitle()));
				$pages[] = $buffer;
				$buffer = $buffer->getParentPage();
			}
			
			$pages = array_reverse($pages);
			$lastPage = array_pop($pages);
			
			foreach($pages as $buffer) {
				$this->addCrumb('page_' . $buffer->getId(), array('label' => $buffer->getPostTitle(), 'link' => $buffer->getPermalink()));
			}
			
			if ($lastPage) {
				$this->addCrumb('page_' . $lastPage->getId(), array('label' => $lastPage->getPostTitle()));
			}
		}
		
		$this->renderLayout();
	}

	/**
	 * Override Magento config by setting a single column template
	 * if specified in Page edit of WP Admin
	 *
	 * @return $this
	 */
	protected function _setPageViewTemplate()
	{
		$page = $this->_initPage();

		$this->_rootTemplates[] = 'page';
				
		$keys = array('onecolumn', '1column', 'full-width');
		$template = $page->getMetaValue('_wp_page_template');

		foreach($keys as $key) {
			if (strpos($template, $key) !== false) {
				$this->getLayout()->helper('page/layout')->applyTemplate('one_column');
				break;
			}
		}
		
		return $this;
	}

	/**
	 * Initialise the post model
	 * Provides redirects for Guid links when using permalinks
	 *
	 * @return false|Fishpig_Wordpress_Model_Page
	 */
	protected function _initPage()
	{
		if ($page = Mage::registry('wordpress_page')) {
			if ($page->getId() && $page->getPostStatus() == 'publish') {
				return $page;
			}
		}

		$page = Mage::getModel('wordpress/page')->load($this->getRequest()->getParam('id'));
			
		if ($page->getId()) {
			Mage::register('wordpress_page', $page);
			
			return $page;
		}

		$page = Mage::getModel('wordpress/page')->load($this->getRequest()->getParam('page_id'));
		
		if ($page->getId()) {
			header('Location: ' . $page->getUrl());
			exit;
		}
		
		
		if (($pageId = Mage::helper('wordpress/router')->getHomepagePageId()) !== false) {
			$page = Mage::getModel('wordpress/page')->load($pageId);

			if ($page->getId()) {
				Mage::register('wordpress_page', $page);
				
				return $page;
			}
		}
		
		return false;
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
}
