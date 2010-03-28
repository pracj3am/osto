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
		if (method_exists(__CLASS__, $name)) { //caching results of static methods
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
		$rc = new \ReflectionClass($class);
		$res = AnnotationsParser::getAll($rc);
		return isset($res[$name]) ? end($res[$name]) : NULL;
	}

	private static function getColumnName($class, $name, $alias = FALSE) {
		//dump($name);
		//dump(get_called_class());
		//dump(static::$PARENTS);
		if ( ($pos = strpos($name, '.')) !== FALSE) {
			$parentName = substr($name, 0, $pos);
			if (isset($class::$PARENTS[$parentName])) {
				$class = $class::$PARENTS[$parentName];
				$name = substr($name, $pos+1);
				$r =  $class::getColumnName($name,$parentName);
				//dump($name); dump($r);
			 	return $r === FALSE ? $r : ($alias ? $alias.Table::ALIAS_DELIM : '').$r;
			} else {
				return FALSE;
			}
		} else {
			$r = ($class::isColumn($name) ? $name : 
					($class::isColumn($class::$PREFIX.'_'.$name) ? 
						$class::$PREFIX.'_'.$name :
						FALSE
					)
			);
		 	return $r === FALSE ? $r : ($alias ? $alias.'.' : '').$r;
		}
		//return (($name == self::ID) || property_exists(get_called_class(), static::$PREFIX.'_'.$name) ? static::$PREFIX.'_' : '').$name;
	}
	
	private static function getTableName($class) {
		if (($tn = $class::getAnnotation('table')) && is_string($tn))
			return $tn;
		else
			return self::fromCamelCase( strrpos($class,'\\') !== FALSE ? substr($class, strrpos($class,'\\')+1) : $class );
	}
	
	private static function getPrefix($class) {
		if (($prefix = $class::getAnnotation('prefix')) && is_string($prefix))
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
		$columns = array($class::$PREFIX.'_'.Table::ID);
		
		$rc = new \ReflectionClass($class);
		foreach ($rc->getProperties() as $rp) {
			if ($rp->isPrivate() && !$rp->isStatic() && strpos($rp->getName(), '_') !== 0) {
				$columns[] = $rp->getName();
			}
		}

		return $columns;
	}

	/**
	 * Vrátí pole názvů sloupců vlastní tabulky a tabulek rodičů 
	 */
	private static function getAllColumns($class) {
		$columns = $class::getColumns();

		foreach ($class::$PARENTS as $parentClass) {
			$columns = array_merge($columns, $parentClass::getColumns());
		}
		
		return array_unique($columns);
	}
	
	
	private static function isColumn($class, $name) {
		return in_array($name, $class::getColumns(), TRUE);
	}
	
	private static function isSelfReferencing($class) {
		return $class::getColumnName('parent_id') && in_array($class, $class::$CHILDREN);
	}
	
	
} 