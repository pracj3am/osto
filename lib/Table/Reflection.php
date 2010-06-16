<?php

namespace isqua\Table;



use isqua\Entity;
use isqua\Table\Helpers;
use isqua\Nette\AnnotationsParser;


/**
 * @todo Implement Nette caching?
 */
abstract class Reflection 
{

	private static $_cache;
	

	public static function __callStatic($name, $arguments) {
		$class = $arguments[0];
		//Debug::dump(array($name,$arguments)); die();
		if (method_exists(__CLASS__, $name) || method_exists(__CLASS__, $name = ltrim($name, '_'))) { //caching results of static methods
			$cachePath = array($name, $class, md5(serialize($arguments)));
			$cache =& self::getCache($cachePath);
			if ($cache === array()) {
				$cache = call_user_func_array(array(__CLASS__, $name), $arguments);
			}
			//Debug::dump(array($cachePath, $cache, self::getCache()));
			//Debug::dump(array($name));
			return $cache;
		}
	}

	private static function &getCache($cachePath = NULL) {
		$cache =& self::$_cache;
		if (is_array($cachePath)) {
			foreach ($cachePath as $part) {
				if (!isset($cache[$part]))
					$cache[$part] = array();

				$cache =& $cache[$part];
			}
		}
		return $cache;
	}
	
	private static function getAnnotation($class, $name) {
		if ($class instanceof \ReflectionClass)
			$rc = $class;
		else
			$rc = new \ReflectionClass($class);
		$res = AnnotationsParser::getAll($rc);
		return isset($res[$name]) ? end($res[$name]) : NULL;
	}
	
	private static function getPropertyAnnotations($class, $prop = NULL) {
		if ($class instanceof \ReflectionProperty)
			$rp = $class;
		else
			$rp = new \ReflectionProperty($class, $prop);
			
		return AnnotationsParser::getAll($rp);
	} 

	private static function getPropertyAnnotation($class, $prop, $name = NULL) {
		if ($class instanceof \ReflectionProperty) {
			$rp = $class;
			$name = $prop;
		} else
			$rp = new \ReflectionProperty($class, $prop);
		$res = AnnotationsParser::getAll($rp);
		return isset($res[$name]) ? end($res[$name]) : NULL;
	}
	
	private static function getColumnName($class, $name, $alias = FALSE) {
		if ( ($pos = strpos($name, '.')) !== FALSE) {
			$parentName = substr($name, 0, $pos);
			$parents = self::_getParents($class);
			if (isset($parents[$parentName])) {
				$class = $parents[$parentName];
				$name = substr($name, $pos+1);
				$r =  self::_getColumnName($class, $name,$parentName);
			 	return $r === FALSE ? $r : ($alias ? $alias.Select::ALIAS_DELIM : '').$r;
			}

			return FALSE;
		}
		$r = ( ($t = self::_getColumns($class)) && isset($t[$name]) ? 
					$t[$name] :
					(self::_isColumn($class, $name) ? 
						$name :
						FALSE
					)
		);
	 	return $r === FALSE ? $r : ($alias ? $alias.'.' : '').$r;
	}
	
	private static function getTableName($class) {
		if (($tn = self::_getAnnotation($class, 'table')) && is_string($tn))
			return $tn;
		else
			return Helpers::fromCamelCase( strrpos($class,'\\') !== FALSE ? substr($class, strrpos($class,'\\')+1) : $class );
	}
	
	private static function getPrefix($class) {
		if (($prefix = self::_getAnnotation($class, 'prefix')) && is_string($prefix))
			return $prefix;
		else
			return strtolower(preg_replace('/[^A-Z0-9]*/', '', $class));
	}
	
	private static function getParents($class) {
		$parents = array();
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($parentClass = self::getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
				$parents[$rp->getName()] = str_replace('%namespace%', $rc->getNamespaceName(), $parentClass);
			}
		}

		return $parents;
	}

	private static function getChildren($class) {
		$children = array();
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($childClass = self::getPropertyAnnotation($rp, 'has_many')) !== NULL) {
				$children[$rp->getName()] = str_replace('%namespace%', $rc->getNamespaceName(), $childClass);
			}
		}

		return $children;
	}

	private static function getSingles($class) {
		$singles = array();
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($singleClass = self::getPropertyAnnotation($rp, 'has_one')) !== NULL) {
				$singles[$rp->getName()] = str_replace('%namespace%', $rc->getNamespaceName(), $singleClass);
			}
		}

		return $singles;
	}

	/**
	 * Vrátí pole názvů sloupců tabulky
	 */
	private static function getColumns($class) {
		$columns = array(Entity::ID=>self::_getPrefix($class).'_'.Entity::ID);
		
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (strpos($cn = $rp->getName(), '_') !== 0) {
				if (self::getPropertyAnnotation($rp, 'has_many') !== NULL) {
					//skip
				} elseif (self::getPropertyAnnotation($rp, 'has_one') !== NULL) {
					//skip
				} elseif (($parentClass = self::getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
					$parentClass = str_replace('%namespace%', $rc->getNamespaceName(), $parentClass);
					$columnName = self::_getPrefix($parentClass).'_'.Entity::ID;
					$columns[$columnName] = $columnName;
				} else {
					$columns[$cn] = ($columnName = self::getPropertyAnnotation($rp, 'column')) && is_string($columnName) ?
						$columnName :
						self::_getPrefix($class).'_'.$rp->getName();
				}
			}
		}

		return $columns;
	}

	private static function getForeignKeys($class) {
		$fks = array();
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (($parentClass = self::getPropertyAnnotation($rp, 'belongs_to')) !== NULL) {
				$parentClass = str_replace('%namespace%', $rc->getNamespaceName(), $parentClass);
				$fk = self::_getPrefix($parentClass).'_'.Entity::ID;
				$fks[$fk] = $parentClass; 
			}
		}
		return $fks;
	}
	
	
	private static function isColumn($class, $name) {
		return in_array($name, self::_getColumns($class), TRUE);
	}
	
	private static function isNullColumn($class, $name) {
		if (($propName = array_search($name, self::_getColumns($class), TRUE)) === FALSE) 
			$propName = $name;
		if (property_exists($class, $propName)) {
			return self::getPropertyAnnotation($class, $propName, 'null') === TRUE;
	 	} 
		 
 		$fks = self::_getForeignKeys($class);
	 	if (isset($fks[$propName]) && ($propName = array_search($fks[$propName], self::_getParents($class))) !== FALSE) {
			return self::getPropertyAnnotation($class, $propName, 'null') === TRUE;
		}
			
		return FALSE;
	}
	
	private static function isSelfReferencing($class) {
		return self::_getColumnName($class, 'parent_id') && in_array($class, self::_getChildren($class));
	}
	
	
} 