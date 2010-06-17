<?php

namespace isqua\Reflection;



use isqua\Entity;
use isqua\Table\Helpers;
use isqua\Table\Select;
use isqua\Nette\AnnotationsParser;



/**
 * @todo Implement Nette caching?
 */
final class EntityReflection extends \ReflectionClass
{

	private $_cache;
	
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
	
	private function getAnnotations() {
		return AnnotationsParser::getAll($this);
	}
	
	private function getAnnotation($name) {
		$res = $this->annotations;
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
	
	private function getTableName() {
		if (($tn = $this->getAnnotation('table')) && is_string($tn))
			return $tn;
		else
			return Helpers::fromCamelCase( strrpos($this->name,'\\') !== FALSE ? substr($this->name, strrpos($this->name,'\\')+1) : $this->name );
	}
	
	private function getPrefix() {
		if (($prefix = $this->getAnnotation('prefix')) && is_string($prefix))
			return $prefix;
		else
			return strtolower(preg_replace('/[^A-Z0-9]*/', '', $this->name));
	}
	
	private function getParents() {
		$parents = array();
		foreach ($this->_getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($parentClass = $this->getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
				$parents[$rp->getName()] = str_replace('%namespace%', $this->_getNamespaceName(), $parentClass);
			}
		}

		return $parents;
	}

	private function getChildren() {
		$children = array();
		foreach ($this->_getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($childClass = $this->getPropertyAnnotation($rp, 'has_many')) !== NULL) {
				$children[$rp->getName()] = str_replace('%namespace%', $this->_getNamespaceName(), $childClass);
			}
		}

		return $children;
	}

	private function getSingles() {
		$singles = array();
		foreach ($this->_getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($singleClass = $this->getPropertyAnnotation($rp, 'has_one')) !== NULL) {
				$singles[$rp->getName()] = str_replace('%namespace%', $this->_getNamespaceName(), $singleClass);
			}
		}

		return $singles;
	}

	/**
	 * Vrátí pole názvů sloupců tabulky
	 */
	private function getColumns() {
		$columns = array(Entity::ID=>$this->prefix.'_'.Entity::ID);
		
		foreach ($this->_getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (strpos($cn = $rp->getName(), '_') !== 0) {
				if ($this->getPropertyAnnotation($rp, 'has_many') !== NULL) {
					//skip
				} elseif ($this->getPropertyAnnotation($rp, 'has_one') !== NULL) {
					//skip
				} elseif (($parentClass = $this->getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
					$parentClass = str_replace('%namespace%', $this->_getNamespaceName(), $parentClass);
					$columnName = $parentClass::getPrefix().'_'.Entity::ID;
					$columns[$columnName] = $columnName;
				} else {
					$columns[$cn] = ($columnName = $this->getPropertyAnnotation($rp, 'column')) && is_string($columnName) ?
						$columnName :
						$this->prefix.'_'.$rp->getName();
				}
			}
		}

		return $columns;
	}

	private function getForeignKeys() {
		$fks = array();
		foreach ($this->_getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($parentClass = $this->getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
				$parentClass = str_replace('%namespace%', $this->_getNamespaceName(), $parentClass);
				$fk = $parentClass::getPrefix().'_'.Entity::ID;
				$fks[$fk] = $parentClass; 
			}
		}
		return $fks;
	}
	
	
	private function isColumn($name) {
		return in_array($name, $this->columns, TRUE);
	}
	
	private function isNullColumn($name) {
		if (($propName = array_search($name, $this->columns, TRUE)) === FALSE) 
			$propName = $name;
		if ($this->_hasProperty($propName)) {
			return $this->getPropertyAnnotation($propName, 'null') === TRUE;
	 	} 
		 
 		$fks = $this->foreignKeys;
	 	if (isset($fks[$propName]) && ($propName = array_search($fks[$propName], $this->parents)) !== FALSE) {
			return $this->getPropertyAnnotation($propName, 'null') === TRUE;
		}
			
		return FALSE;
	}
	
	private function isSelfReferencing() {
		return $this->_getColumnName('parent_id') && in_array($this->name, $this->children);
	}
	
	
} 