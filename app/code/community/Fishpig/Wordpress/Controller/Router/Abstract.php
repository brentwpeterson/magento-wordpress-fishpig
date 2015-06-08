<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

abstract class Fishpig_Wordpress_Controller_Router_Abstract extends Mage_Core_Controller_Varien_Router_Abstract
{
	/**
	 * Callback methods used to generate possible routes
	 *
	 * @var array
	 */
	protected $_routeCallbacks = array();

	/**
	 * Stores the static routes used by WordPress
	 *
	 * @var array
	 */
	protected $_staticRoutes = array();

	/**
	 * The name of the router for the extension
	 * This is used to easily set routes and to define the router
	 *
	 * @var string
	 */
	protected $_frontendRouterName = 'wordpress';

	/**
	 * Create an instance of the router and add it to the queue
	 *
	 * @param Varien_Event_Observer $observer
	 * @return bool
	 */	
	public function initControllerObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}

		$routerClass = get_class($this);

   	    $observer->getEvent()
   	    	->getFront()
   	    		->addRouter($this->_frontendRouterName, new $routerClass);

   	    return true;
	}
	
	/**
	 * Attempt to match the current URI to this module
	 * If match found, set module, controller, action and dispatch
	 *
	 * @param Zend_Controller_Request_Http $request
	 * @return bool
	 */
	public function match(Zend_Controller_Request_Http $request)
	{
		try {
			if (!$this->_canMatch()) {
				return false;
			}
			
			if (($uri = Mage::helper('wordpress/router')->getBlogUri()) === null) {
				return false;	
			}

			$this->_beforeMatch($uri);

			if (($route = $this->_matchRoute($uri)) !== false) {
				return $this->setRoutePath($route['path'], $route['params']);
			}

			Mage::dispatchEvent('wordpress_match_routes_after', array('router' => $this, 'uri' => $uri));

		}
		catch (Exception $e) { 
			Mage::helper('wordpress')->log($e->getMessage());
		}

		return !is_null(Mage::app()->getRequest()->getModuleName())
			&& !is_null(Mage::app()->getRequest()->getControllerName())
			&& !is_null(Mage::app()->getRequest()->getActionName());
	}

	/**
	 * Determine whether WP is available from the root
	 *
	 * @return bool
	 */
	protected function _isWordPressAtRoot()
	{
		$isRootActive = (string)Mage::app()->getConfig()->getNode('modules/Fishpig_Wordpress_Addon_Root/active');
	
		return $isRootActive === 'true';
	}
	
	/**
	 * Called before the route is matched
	 * This can be used to add route callbacks
	 *
	 * @param string $uri
	 * @return $this
	 */
	protected function _beforeMatch($uri)
	{
		Mage::dispatchEvent('wordpress_match_routes_before', array('router' => $this, 'uri' => $uri));		
		
		return $this;
	}

	/**
	 * Set the path and parameters ready for dispatch
	 *
	 * @param array $path
	 * @param array $params = array
	 * @return $this
	 */
	public function setRoutePath($path, array $params = array())
	{
		if (is_string($path)) {
			// Legacy
			$path = explode('/', $path);

			$path = array(
				'module' => $path[0] === '*' ? $this->_frontendRouterName: $path[0],
				'controller' => $path[1],
				'action' => $path[2],
			
			);
		}

		$request = Mage::app()->getRequest();

		$request->setModuleName($path['module'])
			->setRouteName($path['module'])
			->setControllerName($path['controller'])
			->setActionName($path['action']);

		foreach($params as $key => $value) {
			$request->setParam($key, $value);
		}

		$helper = Mage::helper('wordpress/router');
		
		$request->setAlias(
			Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
			ltrim($helper->getBlogRoute() . '/' . $helper->getBlogUri(), '/')
		);

		return true;
	}
	
	/**
	 * Determine whether this module can try and match the URI
	 *
	 * @return bool
	 */
	protected function _canMatch()
	{
		return Mage::helper('wordpress')->isFullyIntegrated() 
			&& Mage::app()->getStore()->getCode() !== 'admin';
	}
	
	/**
	 * Execute callbacks and match generated routes against $uri
	 *
	 * @param string $uri = ''
	 * @return false|array
	 */
	protected function _matchRoute($uri = '')
	{
		$encodedUri = strtolower(str_replace('----slash----', '/', urlencode(str_replace('/', '----slash----', $uri))));
		
		foreach($this->_routeCallbacks as $callback) {
			$this->_staticRoutes = array();

			if (call_user_func($callback, $uri, $this) !== false) {
				foreach($this->_staticRoutes as $route => $data) {
					$match = false;

					if (substr($route, 0, 1) !== '/') {
						$match = $route === $encodedUri || $route === $uri;
					}
					else {
						if (preg_match($route, $uri, $matches)) {
							$match = true;
							
							if (isset($data['pattern_keys']) && is_array($data['pattern_keys'])) {
								array_shift($matches);
								
								if (!isset($data['params'])) {
									$data['params'] = array();
								}

								foreach($matches as $match) {
									if (($pkey = array_shift($data['pattern_keys'])) !== null) {
										$data['params'][$pkey] = $match;
									}
								}	
							}
						}
					}
					
					if ($match) {
						if (isset($data['params']['__redirect_to'])) {
							header('Location: ' . $data['params']['__redirect_to']);
							exit;	
						}
						
						return $data;
					}
				}	
			}
		}

		return false;
	}
	

	/**
	 * Add a callback method to generate new routes
	 *
	 * @param array
	 */
	public function addRouteCallback(array $callback)
	{
		$this->_routeCallbacks[] = $callback;
		
		return $this;
	}
	
	/**
	 * Add a generated route and it's details
	 *
	 * @param array|string $pattern
	 * @param string $path
	 * @param array|null $params = array()
	 * @return $this
	 */
	public function addRoute($pattern, $path, $params = array())
	{
		if (is_array($pattern)) {
			$keys = $pattern[key($pattern)];
			$pattern = key($pattern);
		}
		else {
			$keys = array();
		}

		$path = array_combine(array('module', 'controller', 'action'), explode('/', $path));
		
		if ($path['module'] === '*') {
			$path['module'] = $this->_frontendRouterName;
		}

		$this->_staticRoutes[$pattern] = array(
			'path' => $path,
			'params' => $params,
			'pattern_keys' => $keys,
		);
		
		return $this;
	}
	
	/**
	 * Determine whether to add routes
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return true;
	}
}
