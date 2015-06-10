<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_App extends Fishpig_Wordpress_Helper_Abstract
{
	protected $_errors = array();
	
	static protected $_db = null;
	static protected $_postTypes = null;
	static protected $_taxonomies = null;
	
	public function __construct()
	{
		$this->_initDb();
	}
	
	public function init()
	{
		$this->_initPostTypes();
		$this->_initTaxonomies();

		return $this;
	}
	
	protected function _initDb()
	{
		if (!is_null(self::$_db)) {
			return $this;
		}
		
		self::$_db = false;
		
		/**
		  * Before connecting to the database
		  * Map the WordPress table names with the table prefix
		  */
		Mage::dispatchEvent('wordpress_database_before_connect', array('helper' => $this));
		
		$wordpressEntities = (array)Mage::app()->getConfig()->getNode()->wordpress->database->before_connect->tables;
		$tablePrefix = $this->getTablePrefix();
		
		foreach($wordpressEntities as $entity => $table) {
			Mage::getSingleton('core/resource')->setMappedTableName((string)$table->table, $tablePrefix . $table->table);
		}

		if (!Mage::helper('wordpress/config')->getConfigFlag('wordpress/database/is_shared')) {
			// If database not shared, connect to WP database
			$configs = array('model' => 'mysql4', 'active' => '1', 'host' => '', 'username' => '', 'password' => '', 'dbname' => '', 'charset' => 'utf8');
		
			foreach($configs as $key => $defaultValue) {
				if ($value = $this->getConfigValue('wordpress/database/' . $key)) {
					$configs[$key] = $value;
				}
			}

			foreach(array('username', 'password', 'dbname') as $field) {
				if (isset($configs[$field])) {
					$configs[$field] = Mage::helper('core')->decrypt($configs[$field]);
				}
			}
		
			if (!isset($configs['host']) || !$configs['host']) {
				return $this->addError('Database host not defined.');
			}

			$connection = Mage::getSingleton('core/resource')->createConnection('wordpress', 'pdo_mysql', $configs);
		
			if (!is_object($connection)) {
				return $this;
			}
			
			$connection->getConnection();
			
			if (!$connection->isConnected()) {
				return $this->addError('Unable to connect to WordPress database.');
			}
			
			$db = $connection;
		}
		else {
			$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		}

		try {
			$db->fetchOne(
				$db->select()->from(Mage::getSingleton('core/resource')->getTableName('wordpress/post'), 'ID')->limit(1)
			);
		}
		catch (Exception $e) {
			return $this->addError($e->getMessage())
				->addError(sprintf('Unable to query WordPress database. Is the table prefix (%s) correct?', $tablePrefix));
		}

		$db->query('SET NAMES UTF8');
			
		// After connect
		Mage::dispatchEvent('wordpress_database_after_connect', array('helper' => $this));
		
		$wordpressEntities = (array)Mage::app()->getConfig()->getNode()->wordpress->database->after_connect->tables;

		foreach($wordpressEntities as $entity => $table) {
			Mage::getSingleton('core/resource')->setMappedTableName((string)$table->table, $tablePrefix . $table->table);
		}
		
		self::$_db = $db;
		
		return $this;
	}

	
	protected function _initPostTypes()
	{
		if (!is_null(self::$_postTypes)) {
			return $this;	
		}

		self::$_postTypes = false;

		$transportObject = new Varien_Object(array('post_types' => false));
		
		Mage::dispatchEvent('wordpress_app_init_post_types', array('transport' => $transportObject, 'helper' => $this));

		if ($transportObject->getPostTypes()) {
			self::$_postTypes = $transportObject->getPostTypes();
		}
		else {
			self::$_postTypes = array(
				'post' => Mage::getModel('wordpress/post_type')->setData(array(
					'post_type' => 'post',
					'rewrite' => array('slug' => $this->getWpOption('permalink_structure')),
					'taxonomies' => array('category', 'post_tag'),
					'_builtin' => true,
				)),
				'page' => Mage::getModel('wordpress/post_type')->setData(array(
					'post_type' => 'page',
					'rewrite' => array('slug' => '%postname%'),
					'hierarchical' => true,
					'taxonomies' => array(),
					'_builtin' => true,
				))
			);
		}

		return $this;
	}
		
	public function getDbConnection()
	{
		return self::$_db;
	}
	
	public function getPostTypes()
	{
		$this->init();
		
		return self::$_postTypes;
	}
	
	public function getPostType($type)
	{
		$this->init();
		
		return isset(self::$_postTypes[$type])
			? self::$_postTypes[$type]
			: false;
	}

	public function getTaxonomies()
	{
		$this->init();
		
		return self::$_taxonomies;
	}
	
	public function getTaxonomy($taxonomy)
	{
		$this->init();
		
		return isset(self::$_taxonomies[$taxonomy])
			? self::$_taxonomies[$taxonomy]
			: false;
	}
	
	protected function _initTaxonomies()
	{
		if (!is_null(self::$_taxonomies)) {
			return $this;
		}
		
		self::$_taxonomies = false;
					
		$transportObject = new Varien_Object(array('taxonomies' => false));
		
		Mage::dispatchEvent('wordpress_app_init_taxonomies', array('transport' => $transportObject));
		
		if ($transportObject->getTaxonomies()) {
			self::$_taxonomies = $transportObject->getTaxonomies();
		}
		else {
			self::$_taxonomies = array(
				'category' => Mage::getModel('wordpress/term_taxonomy')->setData(array(
					'type' => 'category',
					'taxonomy_type' => 'category',
					'labels' => array(
						'name' => 'Categories',
						'singular_name' => 'Category',
					),
					'public' => true,
					'hierarchical' => true,
					'rewrite' => array(
						'hierarchical' => true,
						'slug' => Mage::helper('wordpress')->getWpOption('category_base')
					),
					'_builtin' => true,
				)),
				'post_tag' => Mage::getModel('wordpress/term_taxonomy')->setData(array(
					'type' => 'post_tag',
					'taxonomy_type' => 'post_tag',
					'labels' => array(
						'name' => 'Tags',
						'singular_name' => 'Tag',
					),
					'public' => true,
					'hierarchical' => false,
					'rewrite' => array(
						'slug' => Mage::helper('wordpress')->getWpOption('tag_base')
					),
					'_builtin' => true,
				))
			);
		}

		return $this;
	}

	/*
	 * Returns the table prefix used by Wordpress
	 *
	 * @return string
	 */
	public function getTablePrefix()
	{
		return Mage::helper('wordpress/config')->getConfigValue('wordpress/database/table_prefix');
	}
	
	public function addError($msg)
	{
		$this->_errors[] = $msg;
		
		return $this;
	}
	
	public function getTableName($entity)
	{
		return Mage::getSingleton('core/resource')->getTableName($entity);
	}
}
