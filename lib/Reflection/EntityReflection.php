<?php
namespace isqua\Reflection;



use isqua\Entity;
use isqua\Table\Helpers;
use isqua\Table\Select;
use isqua\Nette\AnnotationsParser;
use isqua\Nette\Caching;



if (!defined('ISQUA_TMP_DIR') && defined('TMP_DIR')) {
	define('ISQUA_TMP_DIR', TMP_DIR);
}



final class EntityReflection extends \ReflectionClass
{

	public $children = array();
	public $parents = array();
	public $singles = array();
	public $columns;

	private $_cache;
	private $_properties;
	private $_prefix;
	private $_tableName;


	public function __construct($argument) {
		parent::__construct($argument);

		$this->columns = array(Entity::ID=>$this->prefix.'_'.Entity::ID);
		
		$this->_properties = $this->getAnnotations('property');
		foreach ($this->_properties as &$pa) {
			if ($pa->relation === 'belongs_to') {
				$this->parents[$pa->name] = $pa->type;
				
				$parentClass = $pa->type;
				$columnName = $parentClass::getPrefix().'_'.Entity::ID;
				$this->columns[$columnName] = $columnName;
				$pa->column = $columnName;
			} elseif ($pa->relation === 'has_many') {
				$this->children[$pa->name] = $pa->type;
			} elseif ($pa->relation === 'has_one') {
				$this->singles[$pa->name] = $pa->type;
			} elseif ($pa->relation === FALSE) {
				$this->columns[$pa->name] = is_string($pa->column) ?
					$pa->column :
					($pa->column = $this->prefix.'_'.$pa->name);
			}
		}
	}

	public function __get($name) {
		return $this->__call(Helpers::getter($name), array());
	}
	
	public function __isset($name) {
		return method_exists(__CLASS__, Helpers::getter($name));
	}

	public function __call($name, $arguments) {
		if (method_exists(__CLASS__, $name) || method_exists(__CLASS__, $name = ltrim($name, '_'))) { //caching results of static methods
			$cachePath = array($name, md5(serialize($arguments)));
			$cache =& $this->getCache($cachePath);
			if ($cache === array()) {
				$cache = call_user_func_array(array($this,$name), $arguments);
			}
			return $cache;
		}
	}

	private function &getCache($cachePath = NULL) {
		$cache =& $this->_cache;
		if (is_array($cachePath)) {
			foreach ($cachePath as $part) {
				if (!isset($cache[$part]))
					$cache[$part] = array();

				$cache =& $cache[$part];
			}
		}
		return $cache;
	}
	
	private function getAllAnnotations() {
		return AnnotationsParser::getAll($this);
	}
	
	private function getAnnotations($name) {
		$res = $this->_getAllAnnotations();
		return isset($res[$name]) ? $res[$name] : array();
	}
	
	private function getAnnotation($name) {
		$res = $this->_getAllAnnotations();
		return isset($res[$name]) ? end($res[$name]) : NULL;
	}
	
	private function getPropertyAnnotations($prop) {
		if ($prop instanceof \ReflectionProperty)
			$rp = $prop;
		else
			$rp = $this->getProperty($prop);
			
		return AnnotationsParser::getAll($rp);
	} 

	private function getPropertyAnnotation($prop, $name) {
		$res = $this->_getPropertyAnnotations($prop);
		return isset($res[$name]) ? end($res[$name]) : NULL;
	}
	
	private function getColumnName($name, $alias = FALSE) {
		if ( ($pos = strpos($name, '.')) !== FALSE) {
			$parentName = substr($name, 0, $pos);
			$parents = $this->parents;
			if (isset($parents[$parentName])) {
				$class = $parents[$parentName];
				$name = substr($name, $pos+1);
				$r =  $class::getColumnName($name, $parentName);
			 	return $r === FALSE ? $r : ($alias ? $alias.Select::ALIAS_DELIM : '').$r;
			}

			return FALSE;
		}
		$r = ( ($t = $this->columns) && isset($t[$name]) ? 
					$t[$name] :
					($this->_isColumn($name) ? 
						$name :
						FALSE
					)
		);
	 	return $r === FALSE ? $r : ($alias ? $alias.'.' : '').$r;
	}
	
	private function isEntity() {
		return $this->isSubClassOf('isqua\Entity') && !$this->isAbstract();
	}
	
	private function isExtendedEntity() {
		return $this->_isEntity() && ($pc = $this->getParentClass()) && $pc->name != 'isqua\Entity';
	}
	
	public function getTableName() {
		if (!isset($this->_tableName)) {
			if (($tn = $this->getAnnotation('table')) && is_string($tn))
				$this->_tableName = $tn;
			else
				$this->_tableName = Helpers::fromCamelCase( strrpos($this->name,'\\') !== FALSE ? substr($this->name, strrpos($this->name,'\\')+1) : $this->name );
		}
		
		return $this->_tableName;
	}
	
	public function getPrefix() {
		if (!isset($this->_prefix)) {
			if (($prefix = $this->getAnnotation('prefix')) && is_string($prefix))
				$this->_prefix = $prefix;
			else
				$this->_prefix = strtolower(preg_replace('/[^A-Z0-9]*/', '', $this->name));
		}
		
		return $this->_prefix;
	}
	
	public function getParents() {
		return $this->parents;
	}

	public function getChildren() {
		return $this->children;
	}

	public function getSingles() {
		return $this->singles;
	}

	public function getColumns() {
		return $this->columns;
	}

	public function isColumn($name) {
		return in_array($name, $this->columns, TRUE);
	}
	
	private function isNullColumn($name) {
		foreach ($this->_properties as $pa)
			if ($pa->column === $name) return $pa->null;

		foreach ($this->_properties as $pa)
			if ($pa->name === $name) return $pa->null;
			
		return FALSE;
	}
	
	public function isSelfReferencing() {
		return $this->_getColumnName('parent_id') && in_array($this->name, $this->children);
	}
	
	public static function instantiateCache()
	{
		$cacheStorage = new Caching\FileStorage(ISQUA_TMP_DIR);
		return new Caching\Cache($cacheStorage, 'entityReflection');
	}
	
	public static function create($entityClass)
	{
		$cache = self::instantiateCache();
		if (isset($cache[$entityClass])) {
			return $cache[$entityClass];
		} else {
			return new self($entityClass);
		}
		
	}
	
	public function __destruct() 
	{
		$cache = self::instantiateCache();
		if (!isset($cache[$this->name])) {
			$cache->save($this->name, $this, array(
				Caching\Cache::FILES => array($this->getFileName())
			));
		}
	}
} 