<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Post_TagController extends Fishpig_Wordpress_Controller_Abstract
{
	/**
	 * Set the feed blocks
	 *
	 * @var string
	 */
	protected $_feedBlock = 'tag_view';

	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * @return null|false|Fishpig_Wordpress_Model_Post_Tag
	 */
	public function getEntityObject()
	{
		if ($this->getRequest()->getActionName() === 'list') {
			return null;
		}

		return $this->_initPostTag();
	}
	
	/**
	  * Display the tag page and associated posts
	  *
	  */
	public function viewAction()
	{
		$tag = Mage::registry('wordpress_post_tag');
		
		$this->_addCustomLayoutHandles(array(
			'wordpress_post_tag_view',
			'wordpress_term',
			'wordpress_post_list',
		));
			
		$this->_initLayout();
		
		$this->_rootTemplates[] = 'post_list';

		$this->_title(ucwords($tag->getName()));

		$this->addCrumb('tags', array('label' => $this->__('Tags')));
		$this->addCrumb('tag', array('label' => ucwords($tag->getName())));
		
		$this->renderLayout();
	}
	
	/**
	 * Load user based on URI
	 *
	 * @return Fishpig_Wordpress_Model_User
	 */
	protected function _initPostTag()
	{
		if (($tag = Mage::registry('wordpress_post_tag')) !== null) {
			return $tag;
		}

		$postTag = Mage::getModel('wordpress/post_tag')->load($this->getRequest()->getParam('tag'), 'slug');
		
		if ($postTag->getId() > 0) {
			Mage::register('wordpress_post_tag', $postTag);

			return $postTag;
		}

		return false;
	}
}
