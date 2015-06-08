<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Blog extends Varien_Object
{
	/**
	 * An array of constants parsed from wp-config.php
	 *
	 * @var array
	 */
	protected $_wpConfigValues = array();

	protected $_db = null;
	
	protected $_mappedTableNames;
	
	protected $_ready = null;
	
	/**
	 * Connect to WP and integrate with Magento
	 *
	 * @return $this
	 */
	public function run()
	{
		if (is_null($this->_ready)) {
			$this->_ready = true;

			$this->_parseWpConfig();
			$this->_connectWithDatabase();
			$this->_initEnvironment();
		}
		
		return $this;
	}
	
	/**
	 * Check the wp-config.php file exists and read it in
	 * Parse all PHP constants
	 *
	 * @return $this
	 */
	protected function _parseWpConfig()
	{
		$wpConfigFilename = $this->getPath() . 'wp-config.php';
		
		if (!is_file($wpConfigFilename)) {
			throw new Exception('Unable to find wp-config.php file.');
		}
		
		$wpConfig = file_get_contents($wpConfigFilename);

		$wpConfig = preg_replace("/\n\/\/.*\n/Us", " ", $wpConfig);
		$wpConfig = preg_replace('/\n(\/[*]+.*[*]+\/)/Us', '', $wpConfig);
		$wpConfig = preg_replace('/\s+/', ' ', $wpConfig);
		$wpConfig = str_replace(array('define( ', ' ); ', ' , ', "', "), array('define(', ');', ',', "',"), $wpConfig);

		$inner = "[0-9]+|true|false|'[^']{1,}'";
		
		if (!preg_match_all('/define\([\'"]{1}([a-zA-Z0-9_]+)[\'"]+,(' . $inner . ')\);/s', $wpConfig, $defines)) {
			throw new Exception('Unable to parse wp-config.php file.');
		}

		$constants = array_combine($defines[1], $defines[2]);
		
		foreach($constants as $key => $value) {
			if ($value === 'true') {
				$value = true;
			}
			else if ($value === 'false') {
				$value = false;
			}
			else if (substr($value, 0, 1) === "'" && substr($value, -1) === "'") {
				$value = trim($value, "'");
			}
			else if (is_numeric($value)) {
				$value = (int)$value;
			}
			else {
				echo 'Unknown value: ' . $value . ' for key ' . $key;
				exit;
			}
			
			$this->_wpConfigValues[strtolower($key)] = $value;
		}
		
		if (preg_match('/\$table_prefix = ([\'"]{1})(.*)\\1/sU', $wpConfig, $match)) {
			$this->_wpConfigValues['table_prefix'] =  $match[2];
		}
		
		return $this;
	}
	
	/**
	 * Initialize the WP environment
	 * This includes connecting to the database
	 *
	 * @return $this
	 */
	protected function _connectWithDatabase()
	{
		if (!is_null($this->_db)) {
			return $this;
		}
		
		// Set DB to false in case anything goes wrong
		$this->_db = false;
		
		$dbConfig = array(
			'model' => 'mysql4', 
			'active' => '1', 
			'initStatements' => 'SET NAMES utf8',
			'host' => $this->getWpConfigValue('db_host'),
			'username' => $this->getWpConfigValue('db_user'),
			'password' => $this->getWpConfigValue('db_password'), 
			'dbname' => $this->getWpConfigValue('db_name'), 
			'charset' => 'utf8'
		);
		
		$magentoConfig = (array)Mage::getConfig()->getNode('global/resources/default_setup/connection');
		
		$this->setIsDatabaseShared(
			$dbConfig['host'] === $magentoConfig['host']
			&& $dbConfig['username'] === $magentoConfig['username']
			&& $dbConfig['password'] === $magentoConfig['password']
		);

		$this->_beforeDatabaseConnect();

		$resource = Mage::getSingleton('core/resource');
		
		if ($this->getIsDatabaseShared()) {
			$this->_db = $resource->getConnection('core_read');
		}
		else {
			$connection = $resource->createConnection('wordpress', 'pdo_mysql', $dbConfig);
			
			if (!is_object($connection) || !$connection->isConnected()) {
				throw new Exception('Unable to connect to WordPress database.');
			}

			$select = $connection->select()
				->from(Mage::getSingleton('core/resource')->getTableName('wordpress/post'), 'ID')
				->limit(1);

echo $select;exit;
			try {
				$connection->fetchOne($select);

				$this->_db = $connection;
			}
			catch (Exception $e) {
				Mage::logException($e);				
			}
		}

		return $this;	
	}
	
	/**
	 * Initialize the WP environment
	 * This includes setting up URLs
	 * and checking for the level of integration
	 *
	 * @return $this
	 */
	protected function _initEnvironment()
	{
		$home = $this->getHome();
		$siteurl = $this->getSiteurl();
		
		// Hack to ensure integrated theme
		$home = str_replace('/wp', '/blog', $home);
		
		$magento = rtrim(str_replace('/' . basename($_SERVER['SCRIPT_FILENAME']), '', Mage::getBaseUrl()), '/');
		$slug = false;
				
		// Calculate blog route (aka. slug)
		if (strpos($home, $magento) === 0) {
			$slug = substr($home, strlen($magento)+1);
		}
		
		// Check for theme integration
		$themeIntegrated = $slug && !is_dir(Mage::getBaseDir() . DS . $slug);
		
		// Check for root
		$isAtRoot = $magento === $home && $home !== $siteurl;
		
		// Enable theme integration if at root
		$themeIntegrated = $themeIntegrated || $isAtRoot;
	
		$this->setIsThemeIntegrated($themeIntegrated)	;
		$this->setIsAtRoot($isAtRoot);
		$this->setSiteurl($siteurl);
		$this->setHome($home);
		
		return $this;	
	}

	protected function _beforeDatabaseConnect()
	{
		Mage::dispatchEvent('wordpress_database_before_connect', array('blog' => $this));
		
		$entities = (array)Mage::app()->getConfig()
			->getNode()
				->wordpress->database->before_connect->tables;

		foreach($entities as $entity => $table) {
			$this->setMappedTableName((string)$table->table, $this->getTablePrefix() . $table->table);
		}
			
		return $this;
	}
	
	/**
	 * Get a value that was defined in the wp-config.php file
	 *
	 * @param string $key
	 * @param mixed $default = null
	 * @return mixed
	 */
	public function getWpConfigValue($key, $default = null)
	{
		return isset($this->_wpConfigValues[$key])
			? $this->_wpConfigValues[$key]
			: $default;
	}
	
	/**
	 * Get an option from the WP options database table
	 *
	 * @param string $key
	 * @return string|false
	 */
	public function getOption($key)
	{
		$select = $this->getDb()
			->select()
				->from($this->getMappedTableName('options'), 'option_value')
				->where('option_name=?', $key)
				->limit(1);

		return $this->getDb()->fetchOne($select);
	}
	
	/**
	 * Get the database connection model
	 *
	 * @return 
	 */
	public function getDb()
	{
		$this->run();

		return $this->_db;
	}

	/**
	 * Get the path to WordPress
	 *
	 * @return string
	 */
	public function getPath()
	{
		return Mage::helper('wordpress')->getWordPressPath();
	}
	
	public function getHome()
	{
		return $this->getOption('home');
	}
	
	public function getSiteurl()
	{
		return $this->getOption('siteurl');
	}
	
	public function getTablePrefix()
	{
		return $this->getWpConfigValue('table_prefix');
	}

    public function setMappedTableName($tableName, $mappedName)
    {
        $this->_mappedTableNames[$tableName] = $mappedName;
        
        return $this;
    }

    /**
     * Get mapped table name
     *
     * @param string $tableName
     * @return bool|string
     */
    public function getMappedTableName($tableName)
    {
		return isset($this->_mappedTableNames[$tableName])
			? $this->_mappedTableNames[$tableName]
			: false;
    }
}
