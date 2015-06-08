<?php
/**
 * @category		Fishpig
 * @package		Fishpig_Wordpress
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Router extends Fishpig_Wordpress_Helper_Abstract
{
	/**
	 * Retrieve the blog URI
	 * This is the whole URI after blog route
	 *
	 * @return string
	 */
	public function getBlogUri()
	{
		$cacheKey = 'wp_blog_uri';
		
		if ($this->_isCached($cacheKey)) {
			return $this->_cached($cacheKey);
		}
		
		$pathInfo = strtolower(trim($this->getRequest()->getPathInfo(), '/'));	
		
		if ($this->getBlogRoute() && strpos($pathInfo, $this->getBlogRoute()) !== 0) {
			return null;
		}

		$pathInfo = trim(substr($pathInfo, strlen($this->getBlogRoute())), '/');
		
		if ($pathInfo === '') {
			return '';
		}
		
		$pathInfo = explode('/', $pathInfo);
		
		// Clean off pager and feed parts
		if (($key = array_search($this->getPostPagerVar(), $pathInfo)) !== false) {
			if (isset($pathInfo[($key+1)]) && preg_match("/[0-9]{1,}/", $pathInfo[($key+1)])) {
				$this->getRequest()->setParam($this->getPostPagerVar(), $pathInfo[($key+1)]);
				unset($pathInfo[($key+1)]);
				unset($pathInfo[$key]);
				
				$pathInfo = array_values($pathInfo);
			}
		}
		
		// Clean off feed and trackback variable
		if (($key = array_search($this->getFeedVar(), $pathInfo)) !== false) {
			unset($pathInfo[$key]);
			
			if (isset($pathInfo[$key+1])) {
				$type = $pathInfo[$key+1];
				unset($pathInfo[$key+1]);
			}
			else {
				$type = 'rss2';
			}
			
			$this->getRequest()->setParam($this->getFeedVar(), $type);
		}

		if (($key = array_search($this->getTrackbackVar(), $pathInfo)) !== false) {
			unset($pathInfo[$key]);
			$pathInfo = array_values($pathInfo);
			$this->getRequest()->setParam($this->getTrackbackVar(), 1);
		}
		
		// Remove comments pager variable
		foreach($pathInfo as $i => $part) {
			$results = array();
			if (preg_match("/" . sprintf($this->getCommentPagerVarFormat(), '([0-9]{1,})') . "/", $part, $results)) {
				if (isset($results[1])) {
					unset($pathInfo[$i]);
				}
			}
		}
		
		if (count($pathInfo) == 1 && preg_match("/^[0-9]{1,8}$/", $pathInfo[0])) {
			$this->getRequest()->setParam(Mage::helper('wordpress/post')->getPostIdVar(), $pathInfo[0]);
			
			array_shift($pathInfo);
		}

		$uri = urldecode(implode('/', $pathInfo));
		
		$this->_cache($cacheKey, $uri);
		
		return $uri;
	}
	
	/**
	 * Determine whether the URI is a blog post URI
	 *
	 * @param string $uri
	 * @return bool
	 */
	public function isPostUri($uri)
	{
		return Mage::helper('wordpress/post')->isPostUri($uri);
	}

	/**
	 * Retrieve the page ID set via the query string
	 *
	 * @return int|null
	 */
	public function getPageId()
	{
		return $this->getRequest()->getParam('page_id');
	}
	
	/**
	 * Determine whether the URI is a blog post attachment URI
	 *
	 * @param string $uri
	 * @return bool
	 */
	public function isPostAttachmentUri($uri)
	{
		return Mage::helper('wordpress/post')->isPostAttachmentUri($uri);
	}

		
	/**
	 * Retrieve the Regex pattern used to identify a permalink string
	 * Allows for inclusion of other locale characters
	 *
	 * @return string
	 */
	public function getPermalinkStringRegex($extra = '')
	{
		return '[a-z0-9' . $this->getSpecialUriChars() . '_\-\.' . $extra . ']{1,}';
	}

	/**
	 * Retrieve an array of special chars that can be used in a URI
	 *
	 * @return array
	 */
	public function getSpecialUriChars()
	{
		$chars = array('‘', '’','“', '”', '–', '—', '`');
		
		// Cryllic
//		$chars[] = '\p{Cyrillic}';
			
		return implode('', $chars);	
	}
	
	/**
	 * Retrieve the format variable for the comment pager
	 *
	 * @return string
	 */
	public function getCommentPagerVarFormat()
	{
		return '^comment-page-%s$';
	}
	
	/**
	 * Retrieve the post pager variable
	 *
	 * @return string
	 */
	public function getPostPagerVar()
	{
		return 'page';
	}
	
	/**
	 * Retrieve the feed variable
	 *
	 * @return string
	 */
	public function getFeedVar()
	{
		return 'feed';
	}
	
	/**
	 * Retrieve the trackback variable
	 *
	 * @return string
	 */
	public function getTrackbackVar()
	{
		return 'trackback';
	}
	
	/**
	 * Retrieve the request object
	 *
	 * @return
	 */
	public function getRequest()
	{
		return Mage::app()->getRequest();
	}
	
	/**
	 * Retrieve the search query variable name
	 *
	 * @return string
	 */
	public function getSearchVar()
	{
		return 's';
	}
	
	/**
	 * Retrieve the search route
	 *
	 * @return string
	 */
	public function getSearchRoute()
	{
		return 'search';
	}
	
	/**
	 * Retrieve the current search term
	 *
	 * @return string
	 */
	public function getSearchTerm($escape = false, $key = null)
	{
		if (is_null($key)) {
			$searchTerm = $this->getRequest()->getParam($this->getSearchVar());
		}
		else {
			$searchTerm = $this->getRequest()->getParam($key);
		}
		
		return $escape
			? Mage::helper('wordpress')->escapeHtml($searchTerm)
			: $searchTerm;
	}
	
	/**
	 * Generate an array of URI's based on $results
	 *
	 * @param array $results
	 * @return array
	 */
	public function generateRoutesFromArray($results, $prefix = '')
	{
		$objects = array();
		$byParent = array();

		foreach($results as $key => $result) {
			if (!$result['parent']) {
				$objects[$result['id']] = $result;
			}
			else {
				if (!isset($byParent[$result['parent']])) {
					$byParent[$result['parent']] = array();
				}

				$byParent[$result['parent']][$result['id']] = $result;
			}
		}
		
		if (count($objects) === 0) {
			return false;
		}

		$routes = array();
		
		foreach($objects as $objectId => $object) {
			if (($children = $this->_createArrayTree($objectId, $byParent)) !== false) {
				$objects[$objectId]['children'] = $children;
			}

			$routes += $this->_createLookupTable($objects[$objectId], $prefix);
		}
		
		return $routes;
	}
	
	/**
	 * Create a lookup table from an array tree
	 *
	 * @param array $node
	 * @param string $idField
	 * @param string $field
	 * @param string $prefix = ''
	 * @return array
	 */
	protected function _createLookupTable(&$node, $prefix = '')
	{
		if (!isset($node['id'])) {
			return array();
		}

		$urls = array(
			$node['id'] => ltrim($prefix . '/' . urldecode($node['url_key']), '/')
		);

		if (isset($node['children'])) {
			foreach($node['children'] as $childId => $child) {
				$urls += $this->_createLookupTable($child, $urls[$node['id']]);
			}
		}

		return $urls;
	}
	
	/**
	 * Create an array tree. This is used for creating static URL lookup tables
	 * for categories and pages
	 *
	 * @param int $id
	 * @param array $pool
	 * @param string $field = 'parent'
	 * @return false|array
	 */
	protected function _createArrayTree($id, &$pool)
	{
		if (isset($pool[$id]) && $pool[$id]) {
			$children = $pool[$id];
			
			unset($pool[$id]);
			
			foreach($children as $childId => $child) {
				unset($children[$childId]['parent']);
				if (($result = $this->_createArrayTree($childId, $pool)) !== false) {
					$children[$childId]['children'] = $result;
				}
			}

			return $children;
		}
		
		return false;
	}
	
	/**
	 * If a page is set as a custom homepage, get it's ID
	 *
	 * @return false|int
	 */
	public function getHomepagePageId()
	{
		if (Mage::helper('wordpress')->getWpOption('show_on_front') === 'page') {
			if ($pageId = Mage::helper('wordpress')->getWpOption('page_on_front')) {
				return $pageId;
			}
		}
		
		return false;
	}
	
	/**
	 * If a page is set as a custom homepage, get it's ID
	 *
	 * @return false|int
	 */
	public function getBlogPageId()
	{
		if (Mage::helper('wordpress')->getWpOption('show_on_front') === 'page') {
			if ($pageId = Mage::helper('wordpress')->getWpOption('page_for_posts')) {
				return $pageId;
			}
		}
		
		return false;
	}

}
