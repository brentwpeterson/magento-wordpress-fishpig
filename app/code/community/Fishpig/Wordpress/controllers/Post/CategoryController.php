<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Post_CategoryController extends Fishpig_Wordpress_Controller_Abstract
{
	/**
	 * Set the feed blocks
	 *
	 * @var string
	 */
	protected $_feedBlock = 'category_view';
	
	/**
	 * Used to do things en-masse
	 * eg. include canonical URL
	 *
	 * @return false|Fishpig_Wordpress_Model_Post_Category
	 */
	public function getEntityObject()
	{
		return $this->_initPostCategory();
	}
	
	/**
	 * If a term has been initiated in self_initPostCategory
	 * forward to wordpress/term/view action
	 *
	 * @return $this
	 */
	public function preDispatch()
	{
		parent::preDispatch();
		
		if (($term = Mage::registry('wordpress_term')) !== null) {
			$this->_forceForwardViaException('view', 'term');
			return false;
		}		
		
		return $this;
	}

	/**
	  * Display the category page and list blog posts
	  *
	  */
	public function viewAction()
	{
		$category = Mage::registry('wordpress_category');
		
		$this->_addCustomLayoutHandles(array(
			'wordpress_post_category_view',
			'wordpress_category_'.$category->getId(),
			'wordpress_post_list',
			'wordpress_term',
		));
			
		$this->_initLayout();
		
		$this->_rootTemplates[] = 'post_list';

		$tree = array($category);
		$buffer = $category;
		
		while(($buffer = $buffer->getParentCategory()) !== false) {
			array_unshift($tree, $buffer);
		}

		while(($branch = array_shift($tree)) !== null) {
			$this->addCrumb('category_' . $branch->getId(), array(
				'link' => ($tree ? $branch->getUrl() : null), 
				'label' => $branch->getName())
			);

			$this->_title($branch->getName());
		}

		$this->renderLayout();
	}

	/**
	 * Load the category based on the slug stored in the param 'category'
	 *
	 * @return Fishpig_Wordpress_Model_Post_Categpry
	 */
	protected function _initPostCategory()
	{
		if (($category = Mage::registry('wordpress_category')) !== null) {
			return $category;
		}
		
		$category = Mage::getModel('wordpress/post_category')->load($this->getRequest()->getParam('id'));
	
		if ($category->getId()) {
			Mage::register('wordpress_category', $category);
				
			return $category;
		}

		return false;
	}
}
