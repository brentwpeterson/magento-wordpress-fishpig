<?php
/**
 * @category		Fishpig
 * @package		Fishpig_Wordpress
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Controller_Router extends Fishpig_Wordpress_Controller_Router_Abstract
{
	/**
	 * Remove the AW_Blog route to stop conflicts
	 *
	 * @param Varien_Event_Observer $observer
	 * @return bool
	 */
    public function initControllerBeforeObserver(Varien_Event_Observer $observer)
    {
    	if (Mage::getDesign()->getArea() === 'frontend') {
	    	$node = Mage::getConfig()->getNode('global/events/controller_front_init_routers/observers');
    	
	    	if (isset($node->blog)) {
		    	unset($node->blog);

		    	Mage::getConfig()->setNode('modules/AW_Blog/active', 'false', true);
		    	Mage::getConfig()->setNode('frontend/routers/blog', null, true);
		    }
        }

        return false;
    }
    	
	/**
	 * Initialize the static routes used by WordPress
	 *
	 * @return $this
	 */
	protected function _beforeMatch($uri)
	{
		parent::_beforeMatch($uri);

		if (!$uri) {
			$this->addRouteCallback(array($this, '_getHomepageRoutes'));	
		}
		
		$this->addRouteCallback(array($this, '_getSimpleRoutes'));
		$this->addRouteCallback(array($this, '_getPostCategoryRoutes'));
		$this->addRouteCallback(array($this, '_getPageRoutes'));
		$this->addRouteCallback(array($this, '_getPostRoutes'));
		$this->addRouteCallback(array($this, '_getPostAttachmentRoutes'));
		$this->addRouteCallback(array($this, '_getCustomTaxonomyUris'));
		$this->addRouteCallback(array($this, '_getPostNonCanonicalRoutes'));
		
		return $this;	
	}

	/**
	 * Get route data for different homepage URLs
	 *
	 * @param string $uri = ''
	 * @return $this
	 */
	protected function _getHomepageRoutes($uri = '')
	{
		// NextGEN Gallery fix
		if (Mage::app()->getRequest()->getParam('format') === 'json') {
			return $this->addRoute('', '*/index/forward');
		}

		if ($postId = Mage::app()->getRequest()->getParam('p')) {
			return $this->addRoute('', '*/post/view', array('p' => $postId, 'id' => $postId));
		}

		if (($pageId = $this->_getHomepagePageId()) !== false) {
			return $this->addRoute('', '*/page/view', array('id' => $pageId, 'is_home' => 1));
		}
	
		$this->addRoute('', '*/index/index');
		
		return $this;
	}
	
	/**
	 * Generate the basic simple routes that power WP
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */	
	protected function _getSimpleRoutes($uri = '')
	{
		if (strpos($uri, 'ajax/') === 0) {
			$this->_getAjaxRoutes($uri);
		}

		$this->addRoute(array('/^' . preg_quote(Mage::getSingleton('wordpress/post_tag')->getUriPrefix(), '/') . '\/(.*)$/' => array('tag')), '*/post_tag/view');
		$this->addRoute(array('/^author\/([^\/]{1,})/' => array('author')), '*/author/view');
		$this->addRoute(array('/^([1-2]{1}[0-9]{3})\/([0-1]{1}[0-9]{1})$/' => array('year', 'month')), '*/archive/view');
		$this->addRoute(array('/^([1-2]{1}[0-9]{3})\/([0-1]{1}[0-9]{1})$/' => array('year', 'month')), '*/archive/view');
		$this->addRoute(array('/^([1-2]{1}[0-9]{3})\/([0-1]{1}[0-9]{1})\/([0-3]{1}[0-9]{1})$/' => array('year', 'month', 'day')), '*/archive/view');
		$this->addRoute(array('/^search\/(.*)$/' => array('s')), '*/search/index');
		$this->addRoute('search', '*/search/index', array('redirect_broken_url' => 1)); # Fix broken search URLs
		$this->addRoute('/^index.php/i', '*/index/forward');
		$this->addRoute('/^wp-content\/(.*)/i', '*/index/forwardFile');
		$this->addRoute('/^wp-includes\/(.*)/i', '*/index/forwardFile');
		$this->addRoute('/^wp-cron.php.*/', '*/index/forwardFile');
		$this->addRoute('/^wp-admin[\/]{0,1}$/', '*/index/wpAdmin');
		$this->addRoute('/^wp-pass.php.*/', '*/index/applyPostPassword');
		$this->addRoute('robots.txt', '*/index/robots');
		$this->addRoute('comments', '*/index/commentsFeed');
		$this->addRoute(array('/^newbloguser\/(.*)$/' => array('code')), '*/index/forwardNewBlogUser');
		
		return $this;
	}

	/**
	 * Retrieve routes for the AJAX methods
	 * These can be used to get another store's blogs blocks
	 *
	 * @param string $uri = ''
	 * @return $this
	 */
	protected function _getAjaxRoutes($uri = '')
	{
		$this->addRoute(array('/^ajax\/handle\/([^\/]{1,})[\/]{0,}$/' => array('handle')), '*/ajax/handle');
		$this->addRoute(array('/^ajax\/block\/([^\/]{1,})[\/]{0,}$/' => array('block')), '*/ajax/block');
		
		return $this;
	}

	/**
	 * Generate the post category routes
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */
	protected function _getPostCategoryRoutes($uri = '')
	{
		$categoryModel = Mage::getSingleton('wordpress/post_category');

		if (($base = $categoryModel->getUriPrefix()) !== '') {
			$base = ltrim($base . '/', '/');

			if (strpos(Mage::helper('wordpress/router')->getBlogUri(), $base) !== 0) {
				return false;
			}
		}

		if (($routes = $categoryModel->getAllUris()) !== false) {
			foreach($routes as $routeId => $route) {
				$this->addRoute($base . $route, '*/post_category/view', array('id' => $routeId));
			}
		}

		return $this;
	}

	/**
	 * Check whether the URI is for a post with the non-canonical category URI
	 * If so, redirect to canonical category URL
	 *
	 * @param string $uri = ''
	 * @return $this
	 */
	protected function _getPostNonCanonicalRoutes($uri = '')
	{
		$categoryModel = Mage::getSingleton('wordpress/post_category');

		if (($base = $categoryModel->getUriPrefix()) !== '') {
			$base = ltrim($base . '/', '/');
		}

		if (($routes = $categoryModel->getAllUris()) !== false) {
			foreach($routes as $routeId => $route) {
				if ($base && strpos($route, $base) === 0) {
					$route = substr($route, strlen($base));
				}

				if (preg_match('/^' . preg_quote(rtrim($route, '/'), '/') . '\/([^\/]{1,})[\/]{0,1}$/', $uri, $match)) {
					$category = Mage::getModel('wordpress/post_category')->load($routeId);
					
					if ($category->getId()) {
						$post = Mage::getModel('wordpress/post')->load($match[1], 'post_name');
						
						if ($post->getId()) {
							if ($category->containsPost($post)) {
								$this->addRoute($uri, '*/post/view', array('id' => $post->getId(), '__redirect_to' => $post->getPermalink()));
								break;
							}
						}
					}
				}
			}
		}

		return $this;
	}
	
	/**
	 * Generate the page routers
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */
	protected function _getPageRoutes($uri = '')
	{
		if (($routes = Mage::getResourceSingleton('wordpress/page')->getAllUris()) !== false) {
			$homepagePageId = $this->_getHomepagePageId();
			
			foreach($routes as $routeId => $route) {
				$redirectTo = $homepagePageId && $homepagePageId == $routeId
					? Mage::helper('wordpress')->getUrl()
					: null;
					
				$this->addRoute($route, '*/page/view', array('id' => $routeId, '__redirect_to' => $redirectTo));
			}
			
			return $this;
		}
		
		return false;
	}

	/**
	 * Generate the post routes
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */
	protected function _getPostRoutes($uri = '')
	{
		$routes = Mage::getResourceSingleton('wordpress/post')->getPermalinksByUri($uri);

		if ($routes === false) {
			return false;
		}

		foreach($routes as $routeId => $route) {
			$route = rtrim($route, '/');

			$this->addRoute($route, '*/post/view', array('id' => $routeId));
			$this->addRoute($route . '/feed', '*/post/feed', array('id' => $routeId));
		}

		return $this;
	}

	/**
	 * Generate the post routes
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */
	protected function _getPostAttachmentRoutes($uri = '')
	{
		if (!preg_match('/^(.*)\/attachment\/([^\/]+$)/', $uri, $match)) {
			return $this;
		}

		$attachment = Mage::getModel('wordpress/post')->setPostType('attachment')->load($match[2], 'post_name');
		
		if (!$attachment->getId() || !$attachment->getGuid()) {
			return $this;
		}

		header('Location: ' . $attachment->getData('guid'));
		exit;
	}
	
	/**
	 * Get the custom taxonomy URI's
	 * First check whether a valid taxonomy exists in $uri
	 *
	 * @param string $uri = ''
	 * @return $this
	 */
	protected function _getCustomTaxonomyUris($uri = '')
	{
		$parts = explode('/', $uri);

		$term = Mage::getModel('wordpress/term')->setTaxonomy(array_shift($parts));

		if (($routes = $term->getAllUris()) !== false) {
			foreach($routes as $routeId => $route) {
				$this->addRoute($term->getTaxonomyType() . '/' . $route, '*/term/view', array('id' => $routeId, 'taxonomy' => $term->getTaxonomyType()));
			}
		}
		
		return $this;
	}
	
	/**
	 * If a page is set as a custom homepage, get it's ID
	 *
	 * @return false|int
	 */
	protected function _getHomepagePageId()
	{
		return Mage::helper('wordpress/router')->getHomepagePageId();
	}
	
	/**
	 * Determine whether to add routes
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return (int)Mage::app()->getStore()->getId() !== 0;
	}
}
