<?php

namespace isqua\Table;



use isqua\Table;
use isqua\Nette\AnnotationsParser;


/**
 * @todo Implement Nette caching?
 */
class Helpers 
{

	private static $_cache;
	

	public static function __callStatic($name, $arguments) {
		$class = $arguments[0];
		//Debug::dump(array($name,$arguments)); die();
		if (method_exists(__CLASS__, $name) || method_exists(__CLASS__, $name = ltrim($name, '_'))) { //caching results of static methods
			$cachePath = array(md5(serialize($arguments)));
			array_unshift($cachePath, $class);
			array_unshift($cachePath, $name);
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
			if (isset($class::$PARENTS[$parentName])) {
				$class = $class::$PARENTS[$parentName];
				$name = substr($name, $pos+1);
				$r =  self::_getColumnName($class, $name,$parentName);
			 	return $r === FALSE ? $r : ($alias ? $alias.Table::ALIAS_DELIM : '').$r;
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
			return self::fromCamelCase( strrpos($class,'\\') !== FALSE ? substr($class, strrpos($class,'\\')+1) : $class );
	}
	
	private static function getPrefix($class) {
		if (($prefix = self::_getAnnotation($class, 'prefix')) && is_string($prefix))
			return $prefix;
		else
			return strtolower(preg_replace('/[^A-Z0-9]*/', '', $class));
	}
	
	// helper
	public static function fromCamelCase($name) {
		return strtolower(preg_replace('/(?<=[^_])([A-Z])/', '_\1', $name));
	}
	
	// helper
	public static function toCamelCase($name) {
		return preg_replace_callback('/(?<=[^_])_([^_])/', function($matches){return strtoupper($matches[1]);}, $name);
	}
	
	/**
	 * Vrátí pole názvů sloupců tabulky
	 */
	private static function getColumns($class) {
		$columns = array(Table::ID=>self::_getPrefix($class).'_'.Table::ID);
		
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
			if (strpos($cn = $rp->getName(), '_') !== 0) {
				$columns[$cn] = ($columnName = self::getPropertyAnnotation($rp, 'column')) && is_string($columnName) ?
					$columnName :
					self::_getPrefix($class).'_'.$rp->getName();
			}
		}

		return $columns;
	}

	/**
	 * Vrátí pole názvů sloupců vlastní tabulky a tabulek rodičů 
	 */
	private static function getAllColumns($class) {
		$columns = self::_getColumns($class);

		foreach ($class::$PARENTS as $parentClass) {
			$columns = array_merge($columns, self::_getColumns($parentClass));
		}
		
		return array_unique($columns);
	}
	
	
	private static function isColumn($class, $name) {
		return in_array($name, self::_getColumns($class), TRUE);
	}
	
	private static function isNullColumn($class, $name) {
		if (($propName = array_search($name, self::_getColumns($class), TRUE)) === FALSE) 
			$propName = $name;
		$isNull = self::getPropertyAnnotation($class, $name, 'null');
		return $isNull === TRUE;
	}
	
	private static function isSelfReferencing($class) {
		return self::_getColumnName($class, 'parent_id') && in_array($class, $class::$CHILDREN);
	}
	
	
} 