<?php
namespace isqua;

use dibi;

abstract class Table {
	const ID = 'id';
	const ALIAS = '$this';
	const ALIAS_DELIM = '->';
	
	const VALUE_NOT_SET = 0;
	const VALUE_NOT_MODIFIED = 1;
	const VALUE_MODIFIED = 2;
	
	static $PREFIX = '';
	static $PARENTS = array();
	static $CHILDREN = array();
	static $FIELDS = array();
	static $NULL_COLUMNS = array();
	
	private $__id;
	private $__values = array();
	private $__modified = array();
	private $__parents = array();
	private $__children = array();
	private $__aux = array();
	
	private static $__cache;
	
	function __construct($id = null) {
		$this->id = $id;
		foreach ($this->columns as $column) {
			if ($column != static::$PREFIX.'_'.self::ID) {
				$this->__modified[$column] = self::VALUE_NOT_SET;
				$this->__values[$column] = NULL;
			}
		}
		if (is_array(static::$PARENTS))
			foreach (static::$PARENTS as $parentName=>$parentClass) {
				$this->__parents[$parentName] = NULL;
			}
		if (is_array(static::$CHILDREN))
			foreach (static::$CHILDREN as $childName=>$childClass) {
				$this->$childName = new RowCollection;
			}
	}
	
	public function load($withParents = FALSE, $withChildren = FALSE) {
		
		if ($this->id) {
			$row = dibi::fetch(
				'SELECT * FROM `'.static::getTableName().'` ' .
				'WHERE %and', array(static::$PREFIX.'_'.self::ID=>$this->id)
			);
			if ($row) {
				foreach ($row as $name=>$value) 
					if ($name != (static::$PREFIX.'_'.self::ID)) {
						$this->$name = $value;
						$this->__modified[$name] = self::VALUE_NOT_MODIFIED;
					}
			} else return FALSE;
			
			// parents
			if ($withParents)
				$this->loadParents();
			
			//children
			if ($withChildren)
				$this->loadChildren();
			
			return $this;
			
		} else return NULL;
	}
	
	public function loadParents($parentNames = array(), $withChildren = FALSE) {
		if (empty($parentNames)) $parentNames = array_keys(static::$PARENTS); 
	
		foreach (static::$PARENTS as $parentName=>$parentClass) {
			if (in_array($parentName, $parentNames)) 
				if ($this->{$parentClass::$PREFIX.'_'.self::ID}) {
					$parentEntity = new $parentClass($this->{$parentClass::$PREFIX.'_'.self::ID});
					$parentEntity->load(FALSE, $withChildren);
					$this->{is_string($parentName) ? $parentName : $parentClass::getVariableName()} = $parentEntity; 
				}
		}
	}
	
	public function loadChildren($childrenNames = array(), $where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		if ($this->id) {
			if (empty($childrenNames)) $childrenNames = array_keys(static::$CHILDREN); 
			foreach (static::$CHILDREN as $childName=>$childClass) {
				if (in_array($childName, $childrenNames)) {
					if (get_class($this) == $childClass)//load children of the same class
						$fk = 'parent_id';
					else 						
						$fk = static::$PREFIX.'_'.self::ID;
					$whereTmp = array_merge(
						isset($where[$childName]) ? $where[$childName] : array(), 
						array($fk=>(int)$this->id)
					);
					$sortTmp = isset($sort[$childName]) ? $sort[$childName] : array();
					$limitTmp = isset($limit[$childName]) ? $limit[$childName] : array();
					$this->{is_string($childName) ? $childName : $childClass::getVariableName()} = $childClass::getAll($whereTmp, $sortTmp, $limitTmp, $withParents);
				}
			}
		} else return NULL;
	}
	
	public function getParent($root = false) {
		if (static::isSelfReferencing() && $this->parent_id) {
			$parent = new static($this->parent_id); 
			$parent->load();
			if ($root)
				while (!is_null($parent->parent_id)) {
					$parent = new static($parent->parent_id);
					$parent->load(); 
				}
			return $parent;
		} else return NULL;
	}
	
	public function getValuesForSave() {
		//$values = array_map(function($name){return static::getColumnName($name);},$this->values);
		$values = $this->values;
		foreach ($values as $key=>$value) {
			if ($key == self::ID) continue; // primární klíč vždy potřebujeme
			if (!is_scalar($value) && !is_null($value)) unset($values[$key]);
			// ukládáme jen hodnoty, které se změnily
			elseif ($this->id && $this->__modified[static::getColumnName($key)] !== self::VALUE_MODIFIED) {
				$values[$key] = '`'.static::getColumnName($key).'`';
			}
		}
		return $values;
	}
	
	public function save() {
		//uložíme rodiče
		if (is_array($this->__parents))
			foreach ($this->__parents as $parentName=>$parentEntity) {
				if (!is_null($parentEntity)) {
					$parentEntity->save();
					$this->{$parentEntity::$PREFIX.'_'.self::ID} = $parentEntity->id;
				}
			}

		//$values = array_map(function($name){return static::getColumnName($name);},$this->values);
		$values = $v = $this->getValuesForSave();
		foreach ($values as $key=>&$value) {
			if (is_null($value) && !in_array($key, static::$NULL_COLUMNS)) { //nemůže být null, neukládáme
				//dump('NULL COLUMN!!',array(get_class($this),$values));
				/** @todo vyřešit lépe tohle tiché neuložení - třeba jen unset */
				return FALSE;
			}
		}
		
		static::replaceKeys($values);
		foreach ($values as $key => $value) {
			if (strpos($value, '`') === 0) {
				unset($values[$key]);
				$values[$key.'%n'] = str_replace('`', '', $value); //%n modifier for dibi
			}
		}
		if ($values) {
			$valuesWithoutPK = $values;
			unset($valuesWithoutPK[static::$PREFIX.'_'.self::ID]);
			
			dibi::query(
				'INSERT INTO `'.static::getTableName().'`', $values,
				'ON DUPLICATE KEY UPDATE '.static::$PREFIX.'_'.self::ID.'=LAST_INSERT_ID('.static::$PREFIX.'_'.self::ID.') 
				%if', $valuesWithoutPK, ', %a', $valuesWithoutPK, '%end'
			);
			$this->afterSave($v);
			//dibi::dump();//die();
			if (!$this->id) {
				$id = dibi::insertId();
				if ($this->id && $this->id != $id) throw new Exception('ID changed!');
				$this->id = $id; 
			}
		}
		
		// a uložíme děti
		if (is_array($this->__children))
			foreach ($this->__children as $childName=>$childEntities)
				foreach ($childEntities as $i=>$childEntity) {
					$childEntity->{static::$PREFIX.'_'.self::ID} = $this->id;
					$childEntity->save();
				}
				
		return TRUE;
	}
	
	protected function afterSave(&$values) {
		
	}
	
	public function delete() {
		if ($this->id) {
			dibi::query(
				'DELETE FROM '.static::getTableName().' WHERE %and',
					array(static::$PREFIX.'_'.self::ID=>$this->id),'LIMIT 1'
			);
		}
		// mazání children zajištěno na úrovni databáze
	}
	
	public function copy() {
		$copy = clone $this;
		$copy->id = NULL;
		if (is_array($copy->__parents))
			foreach ($copy->__parents as $parentName=>$parentEntity) {
				if ($parentEntity !== NULL)
					$copy->__parents[$parentName] = $this->__parents[$parentName]->copy();
			}
		if (is_array($copy->__children))
			foreach ($copy->__children as $childName=>$childEntities)
				foreach ($copy->__children[$childName] as $i=>$childEntity) {
					$copy->__children[$childName][$i] = $this->__children[$childName][$i]->copy();
				}
				
		return $copy;
		
	}
	
	public function getValues() {
		$values = array();
		foreach ($this->__values as $name=>$value) {
			if (strpos($name, static::$PREFIX.'_') === 0) $pos = strlen(static::$PREFIX)+1;
			else $pos = 0;
			$values[substr($name,$pos)] = $value;
		}
		if ($this->__id) {
			$values[self::ID] = $this->__id;
		}
		foreach (static::$PARENTS as $parentName=>$parentClass) {
			if (isset($this->$parentName)) 
				$values[$parentName] = $this->$parentName->values;
		}
		foreach (static::$CHILDREN as $childName=>$childClass) {
			if (isset($this->$childName) && is_a($childArray = $this->$childName, 'ArrayObject')) 
				foreach ($childArray as $i=>$childEntity)
					$values[$childName][$i] = $childEntity->values;
		}
		return $values;
	}
	
	public function __get($name) {
		if ($name == 'id' || $name == static::$PREFIX.'_'.self::ID) {
			return $this->__id;
		} elseif (strpos($name, '_') === 0 && ($_name = substr($name, 1)) && self::getColumnName($_name) && array_key_exists(self::getColumnName($_name), $this->__values)) {
			return $this->__values[self::getColumnName($_name)];
		} elseif (method_exists($this, ($m_name = 'get'.ucfirst(self::toCamelCase($name))) )) {//get{Name}
			return $this->{$m_name}();
		} elseif (self::getColumnName($name) && array_key_exists(self::getColumnName($name), $this->__values)) {
			return $this->__values[self::getColumnName($name)];
		} elseif (array_key_exists($name, $this->__parents)) {
			return $this->__parents[$name];
		} elseif (array_key_exists($name, $this->__children)) {
			return $this->__children[$name];
		} elseif (array_key_exists($name, $this->__aux)) {
			return $this->__aux[$name];
		} elseif (preg_match('/^(.*)_datetime$/', $name, $matches) && isset($this->{$matches[1]})) {
			return new \DateTime($this->{$matches[1]});
		} elseif (static::isCallable( $method = 'get'.ucfirst($name) )) {//static get{Name}
			//Debug::dump($method);
			return static::$method();
		/*} else {
			return $this->$name;*/
		}
	}
	
	public function __set($name, $value) {
		if ($name == 'values') {
			if (is_array($value) || is_object($value)) {
				foreach ($value as $key=>$val) {
					if ($this->__isset($key))
						$this->$key = $val;
				}
				if (is_array($value) && isset($value[static::$PREFIX.'_'.self::ID])) {
					$this->id = $value[static::$PREFIX.'_'.self::ID];
				}
				if (is_object($value) && isset($value->{static::$PREFIX.'_'.self::ID})) {
					$this->id = $value->{static::$PREFIX.'_'.self::ID};
				}
				foreach (static::$PARENTS as $parentName=>$parentClass) {
					if (is_array($value) && isset($value[$parentName]) || isset($value->$parentName)) { 
						if (!isset($this->$parentName)) $this->$parentName = new $parentClass();
						$this->$parentName->values = is_array($value) ? $value[$parentName] : $value->$parentName;
					}
				} 
				foreach (static::$CHILDREN as $childName=>$childClass) {
					if (is_array($value) && isset($value[$childName]) && is_array($childArray = $value[$childName]) || isset($value->$childName) && is_array($childArray = $value->$childName)) { 
						$childEntities = isset($this->$childName) ? $this->$childName : new RowCollection();
						foreach ($childArray as $i=>$childValues) {
							if (!isset($childEntities[$i])) $childEntities[$i] = new $childClass();
							$childEntities[$i]->values = $childValues;
						}
						if ($childEntities)
							$this->$childName = $childEntities;
					}
				}
			}
		} elseif (is_object($value) && in_array(get_class($value), static::$PARENTS)) {
			$this->__parents[$name] = $value;
		} elseif (($value instanceof RowCollection) && in_array($name, array_keys(static::$CHILDREN))) {
			if ($value->isEmpty() || in_array($value->getClass(), static::$CHILDREN))
				$this->__children[$name] = $value;
			else throw new \Exception('The collection of objects ('.$name.') that have class '.$value->getClass().' not defined in CHILDREN');
		} elseif ($name == 'id' || $name == static::$PREFIX.'_'.self::ID) {
			$newId = intval($value) === 0 ? NULL : intval($value);
			if ($this->__id !== $newId && !is_null($this->__id))
				array_map(function($item){return Table::VALUE_MODIFIED;}, $this->__modified);
			
			$this->__id = $newId;
				
		} elseif ($_name = static::getColumnName($name)) {
			if ($this->__values[$_name] !== $value)
				$this->__modified[$_name] = self::VALUE_MODIFIED;
			
			$this->__values[$_name] = $value;
		} else {
			//throw new \Exception('Undefined property '.$name.' (class '.get_class($this).')');
			$this->__aux[$name] = $value;
		}
	}
	
	public function __isset($name) {
		if (
			$name == self::ID ||
			self::getColumnName($name) && array_key_exists(static::getColumnName($name), $this->__values) || 
			array_key_exists($name, $this->__children) ||
			(array_key_exists($name, $this->__parents) && !is_null($this->__parents[$name])) ||
			array_key_exists($name, $this->__aux)
		) {
			return true;
		} else {
			return false;
		}
	}
	
	public function __clone() {
		if (is_array($this->__parents))
			foreach ($this->__parents as $parentName=>$parentEntity) {
				if ($parentEntity !== NULL)
					$this->__parents[$parentName] = clone $this->__parents[$parentName];
			}
		if (is_array($this->__children))
			foreach ($this->__children as $childName=>$childEntities) {
				$this->__children[$childName] = clone $this->__children[$childName];
				foreach ($this->__children[$childName] as $i=>$childEntity) {
					$this->__children[$childName][$i] = clone $this->__children[$childName][$i];
				}
			}
	}
	
	public function __call($name, $arguments) {
		if (strpos($name, 'load') === 0) {//load{Parent} or load{Children}
			$varName = strtolower(substr($name, 4, 1)).substr($name, 5);
			if (array_key_exists($varName, static::$PARENTS)) {
				$parentName = $varName;
				$withChildren = isset($arguments[0]) && $arguments[0];
				return $this->loadParents(array($parentName), $withChildren);
			} elseif (array_key_exists($varName, static::$CHILDREN)) {
				$childName = $varName;
				$where = isset($arguments[0]) ? array($childName=>$arguments[0]) : array();
				$sort = isset($arguments[1]) ? array($childName=>$arguments[1]) : array();
				$limit = isset($arguments[2]) ? array($childName=>$arguments[2]) : array();
				$withParents = isset($arguments[3]) && $arguments[3];
				return $this->loadChildren(array($childName), $where, $sort, $limit, $withParents);
			}
		} else {
			return static::__callStatic($name, $arguments); //for non-static context
		}
	}
	
	public static function __callStatic($name, $arguments) {
		$class = get_called_class();
		//Debug::dump(array($name,$arguments)); die();
		if (method_exists($class, $name.'_cached')) { //caching results of static methods
			$cachePath = array(serialize($arguments));
			array_unshift($cachePath, $class);
			array_unshift($cachePath, $name);
			$cache =& self::getCache($cachePath);
			if ($cache === array()) {
				$cache = call_user_func_array(array($class, $name.'_cached'), $arguments);
			}
			//Debug::dump(array($cachePath, $cache, self::getCache()));
			//Debug::dump(array($name));
			return $cache;
		/*} else {
			return call_user_func_array(array($class, $name), $arguments);*/
		}
	}
	
	public static function isCallable($method) {
		return method_exists(get_called_class(), $method) || method_exists(get_called_class(), $method.'_cached');
	}
	
	private static function &getCache($cachePath = NULL) {
		$cache =& self::$__cache;
		if (is_array($cachePath)) {
			foreach ($cachePath as $part) {
				if (!isset($cache[$part]))
					$cache[$part] = array();

				$cache =& $cache[$part];
			}
		}
		return $cache;
	}
	
	public static function count($where = array()) {
		return (int)dibi::fetchSingle(
			static::getSql(	array('COUNT(*)%ex'=>'count'), $where, array(), array(), TRUE ) //vždy s parents? co třeba podle where?
		);
	}
	
	public static function getOne_($where = array(), $sort = array(), $limit = array()) {
		return static::getOne($where, $sort, $limit, TRUE);
	}

	public static function getOne($where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		$rows = static::getAll($where, $sort, $limit, $withParents);
		return $rows->getFirst();
	}
	
	public static function getAll_($where = array(), $sort = array(), $limit = array()) {
		return static::getAll($where, $sort, $limit, TRUE);
	}

	public static function getAll($where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		return static::getFromSql(
			$withParents,
			static::getSql(
				array('*'),
				$where, $sort, $limit, $withParents
			)
		);
	}
	
	public static function getFromSql($withParents) {
		$args = func_get_args();
		if (is_bool($withParents)) {
			array_shift($args);
		} else {
			$withParents = FALSE;
		}
		//dibi::test($args);
		$cursor = dibi::fetchAll($args);
		$rows = new RowCollection();
		foreach ($cursor as $row) {
			$model_class = get_called_class();
			$entity = new $model_class($row->{static::$PREFIX.'_'.self::ID});
			$entity->values = $row;
			//$entity->loadChildren();
			if ($withParents)
				foreach (static::$PARENTS as $parentName=>$parentClass) {
					$parentEntity = new $parentClass();
					$parentEntity->values = $row;
					/**
					 * @todo FUJ - lépe!
					 */
					foreach ($parentClass::$PARENTS as $supParentName=>$supParentClass) {
						$supParentEntity = new $supParentClass();
						$supParentEntity->values = $row;
						$parentEntity->{is_string($supParentName) ? $supParentName : $supParentClass::getVariableName()} = $supParentEntity; 
					}
					$entity->{is_string($parentName) ? $parentName : $parentClass::getVariableName()} = $parentEntity; 
				}
			
			$rows[$row->{static::$PREFIX.'_'.self::ID}] = $entity;
		}
		return $rows;		
	}
	
	public static function getColumn_($column = 'name', $where = array(), $sort = array(), $limit = array(), $concatNamesInTree = FALSE) {
		return static::getColumn($column, $where, $sort, $limit, $concatNamesInTree, TRUE);
	}
	
	public static function getColumn($column = 'name', $where = array(), $sort = array(), $limit = array(), $concatNamesInTree = FALSE, $withParents = FALSE) {
		
		$cursor = dibi::fetchAll(
			static::getSql(
				array(
					static::$PREFIX.'_'.self::ID=>'id',
					static::getColumnName($column)=>'name'
				),
				$where, $sort, $limit, $withParents
			)
		);
		$rows = array();
		foreach ($cursor as $row) {
			if (static::isSelfReferencing() && ($children = static::getColumn($column, array_merge( $where, array('parent_id'=>$row->id) ), $sort)) ) {
				
				$rows[$row->name] = 
					array($row->id => $row->name) +  
					($concatNamesInTree ? array_map(function($_)use ($row){return $row->name.' - '.$_;}, $children) : $children);
			} else {
				$rows[$row->id] = $row->name;
			}
		}
		return $rows;
	}
	
	/**
	 * 
	 * @param $columns
	 * @param $where
	 * @param $sort
	 * @param $limit
	 * @return SQL string
	 */
	protected static function getSql($columns = array('*'), $where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		//dibi::test(
		return dibi::sql(
			'SELECT %n', $columns,
			' FROM '.static::getFromClause($withParents).
			static::getWhereClause($where, $sort, $limit)
		);
		//return 'SELECT * FROM '.static::getFromClause() . ' LIMIT 1 ';
	}
	
	protected static function getFromClause($withParents = FALSE, $alias = self::ALIAS) {

		$from = '`'.static::getTableName().'` AS `'.$alias.'`';
		if ($withParents)
			foreach (static::$PARENTS as $parentName=>$parentClass) {
				if ($parentClass != get_called_class())
					$from .= ' LEFT JOIN (' . $parentClass::getFromClause($withParents, $alias.self::ALIAS_DELIM.$parentName) . ') '. 
						'ON (`'.$alias.'.'.$parentClass::$PREFIX.'_'.self::ID.'`=`'.$alias.self::ALIAS_DELIM.$parentName.'.'.$parentClass::$PREFIX.'_'.self::ID.'`)';
			}
		/*foreach (static::$CHILDREN as $childClass) {
			if ($childClass != get_called_class())
				$from .= ' LEFT JOIN `' . $childClass::getTableName() . '` USING(`'.static::$PREFIX.'_'.self::ID.'`)';
		}*/
		return $from;
	}
	
	protected static function getWhereClause($where = array(), $sort = array(), $limit = array(), $alias = self::ALIAS) {
		static::replaceKeys($where, $alias);
		static::replaceKeys($sort, $alias);
		
		foreach ($where as $column=>$value) {
			if (is_string($value) && strlen(trim($value, '%')) != strlen($value)) {
				$where[] = array('`'.$column.'` LIKE %s', $value);
				unset($where[$column]);
			}
		}
		array_map(function($item){
			$item = $item == 1 ? 'asc' : 'desc';
		}, $sort);		
		if (!is_array($limit)) $limit = array($limit);

		//dibi::test(
		return dibi::sql(
			'%if', $where, 'WHERE %and', $where, '%end',
			'%if', $sort, 'ORDER BY %by', $sort, '%end', 
			'%if', $limit && is_array($limit) , ' LIMIT %i, %i', key($limit), current($limit), '%end'
		);
	}
	
	private static function replaceKeys(&$array, $alias = FALSE) {
		$newArray = array();
		foreach ($array as $key=>$item) { 
			if ($column = static::getColumnName($key, $alias))
				$newArray[$column] = $item;
			elseif (is_int($key)) //numeric index
				$newArray[$key] = $item;
			else 
				$newArray[$key] = $item; //zkusíme ho nechat na pokoj
		} 
		$array = $newArray;
	}
	
	public static function getColumnName_cached($name, $alias = FALSE) {
		//dump($name);
		//dump(get_called_class());
		//dump(static::$PARENTS);
		if ( ($pos = strpos($name, '.')) !== FALSE) {
			$parentName = substr($name, 0, $pos);
			if (isset(static::$PARENTS[$parentName])) {
				$class = static::$PARENTS[$parentName];
				$name = substr($name, $pos+1);
				$r =  $class::getColumnName($name,$parentName);
				//dump($name); dump($r);
			 	return $r === FALSE ? $r : ($alias ? $alias.self::ALIAS_DELIM : '').$r;
			} else {
				return FALSE;
			}
		} else {
			$r = (static::isColumn($name) ? $name : 
					(static::isColumn(static::$PREFIX.'_'.$name) ? static::$PREFIX.'_'.$name :
						 FALSE));
		 	return $r === FALSE ? $r : ($alias ? $alias.'.' : '').$r;
		}
		//return (($name == self::ID) || property_exists(get_called_class(), static::$PREFIX.'_'.$name) ? static::$PREFIX.'_' : '').$name;
	}
	
	public static function getTableName_cached() {
		return self::fromCamelCase( substr(get_called_class(), strrpos(get_called_class(),'\\')+1) );
	}
	
	// helper
	public static function fromCamelCase($name) {
		return strtolower(substr(preg_replace('/([A-Z][a-z]*)/', '_\1', $name), 1));
	}
	
	// helper
	public static function toCamelCase($name) {
		return preg_replace_callback('/(.)_(.)/', function($matches){return $matches[1].strtoupper($matches[2]);}, $name);
	}
	
	/**
	 * Vrátí pole názvů sloupců tabulky
	 */
	public static function getColumns_cached() {
		$columns = array(static::$PREFIX.'_'.self::ID);
		
		$rc = new \ReflectionClass(get_called_class());
		foreach ($rc->getProperties() as $rp) {
			if ($rp->isPrivate() && !$rp->isStatic() && strpos($rp->getName(), '__') !== 0) {
				$columns[] = $rp->getName();
			}
		}

		return $columns;
	}

	/**
	 * Vrátí pole názvů sloupců vlastní tabulky a tabulek rodičů 
	 */
	public static function getAllColumns_cached() {
		$class = get_called_class();

		$columns = static::getColumns();

		foreach (static::$PARENTS as $parentClass) {
			$columns .= array_merge($columns, $parentClass::getColumns());
		}
		
		return array_unique($columns);
	}
	
	
	public static function isColumn($name) {
		return in_array($name, static::getColumns(), TRUE);
	}
	
	/**
	 * Z názvu třídy vytvoří název proměnné (pouze zmenší první písmeno unqualified name)
	 * Používá se v loadChildren
	 * @return string
	 */
	public static function getVariableName_cached() {
		$className = get_called_class();
		return strtolower($className{strrpos($className, '\\')+1}).substr($className, strrpos($className, '\\')+2);
	}
	
	public static function isSelfReferencing_cached() {
		return static::getColumnName('parent_id') && in_array(get_called_class(), static::$CHILDREN);
	}
	
	public static function htmlLetterEntityDecode($string) {
		$trans_tbl = get_html_translation_table(HTML_ENTITIES, ENT_NOQUOTES);
		foreach ($trans_tbl as $code => $ent) {
			if (ord($code) < 192) unset($trans_tbl[$code]);
		}
		$trans_tbl = array_flip($trans_tbl);
		$trans_tbl = array_map(function($c){return @iconv('ISO-8859-1', 'UTF-8', $c);}, $trans_tbl);
		$trans_tbl['&scaron;'] = 'š';
		$trans_tbl['&Scaron;'] = 'Š';
		return strtr($string, $trans_tbl);
		
	}
	
}
?>