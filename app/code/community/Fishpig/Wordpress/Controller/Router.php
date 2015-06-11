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
		$this->addRouteCallback(array($this, '_getPostRoutes'));
		$this->addRouteCallback(array($this, '_getTaxonomyRoutes'));
		
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
		if ($postId = Mage::app()->getRequest()->getParam('p')) {
			return $this->addRoute('', '*/post/view', array('p' => $postId, 'id' => $postId));
		}

		if (($pageId = $this->_getHomepagePageId()) !== false) {
			return $this->addRoute('', '*/post/view', array('id' => $pageId, 'post_type' => 'page', 'home' => 1));
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
	 * Generate the post routes
	 *
	 * @param string $uri = ''
	 * @return false|$this
	 */
	protected function _getPostRoutes($uri = '')
	{
		if (($routes = Mage::getResourceSingleton('wordpress/post')->getPermalinksByUri($uri)) === false) {
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
	 * Get the custom taxonomy URI's
	 * First check whether a valid taxonomy exists in $uri
	 *
	 * @param string $uri = ''
	 * @return $this
	 */
	protected function _getTaxonomyRoutes($uri = '')
	{
		foreach(Mage::helper('wordpress/app')->getTaxonomies() as $taxonomy) {
			if (($routes = $taxonomy->getUris($uri)) !== false) {

				foreach($routes as $routeId => $route) {
					$this->addRoute($route, '*/term/view', array('id' => $routeId, 'taxonomy' => $taxonomy->getTaxonomyType()));
					$this->addRoute(rtrim($route, '/') . '/feed', '*/term/feed', array('id' => $routeId, 'taxonomy' => $taxonomy->getTaxonomyType()));
				}
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
