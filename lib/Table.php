<?php
namespace isqua;



use dibi;
use isqua\Table\Helpers;



abstract class Table 
{
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
	
	private $_id;
	private $_values = array();
	private $_modified = array();
	private $_parents = array();
	private $_children = array();
	private $_loaded;
	private $_self_loaded = false;
	private $_aux = array();
	
	function __construct($id = null) {
		$this->id = $id;
		foreach ($this->columns as $column) {
			if ($column != static::getPrefix().'_'.self::ID) {
				$this->_modified[$column] = self::VALUE_NOT_SET;
				$this->_values[$column] = NULL;
			}
		}
		if (is_array(static::$PARENTS))
			foreach (static::$PARENTS as $parentName=>$parentClass) {
				$this->_parents[$parentName] = NULL;
				$this->_loaded[$parentName] = FALSE;
			}
		if (is_array(static::$CHILDREN))
			foreach (static::$CHILDREN as $childName=>$childClass) {
				$this->_children[$childName] = new RowCollection;
				$this->_loaded[$childName] = FALSE;
			}
	}
	
	public function load($withParents = FALSE, $withChildren = FALSE) {
		
		if ($this->id) {
			$row = dibi::fetch(
				'SELECT * FROM `'.static::getTableName().'` ' .
				'WHERE %and', array(static::getPrefix().'_'.self::ID=>$this->id)
			);
			if ($row) {
				foreach ($row as $name=>$value) 
					if ($name != (static::getPrefix().'_'.self::ID)) {
						$this->$name = $value;
						$this->_modified[$name] = self::VALUE_NOT_MODIFIED;
					}
				$this->_self_loaded = TRUE;
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
				if ($this->{$parentClass::getPrefix().'_'.self::ID}) {
					$parentEntity = new $parentClass($this->{$parentClass::getPrefix().'_'.self::ID});
					$parentEntity->load(FALSE, $withChildren);
					$this->$parentName = $parentEntity;
					$this->_loaded[$parentName] = TRUE; 
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
						$fk = static::getPrefix().'_'.self::ID;
					$whereTmp = array_merge(
						isset($where[$childName]) ? $where[$childName] : array(), 
						array($fk=>(int)$this->id)
					);
					$sortTmp = isset($sort[$childName]) ? $sort[$childName] : array();
					$limitTmp = isset($limit[$childName]) ? $limit[$childName] : array();
					$this->$childName = $childClass::getAll($whereTmp, $sortTmp, $limitTmp, $withParents);
					$this->_loaded[$childName] = TRUE;
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
			elseif ($this->id && $this->_modified[static::getColumnName($key)] !== self::VALUE_MODIFIED) {
				$values[$key] = '`'.static::getColumnName($key).'`';
			}
		}
		return $values;
	}
	
	public function save() {
		//uložíme rodiče
		foreach (static::$PARENTS as $parentName=>$parentClass) {
			if (isset($this->$parentName)) {
				$parentEntity = $this->$parentName;
				$parentEntity->save();
				$this->{$parentEntity::getPrefix().'_'.self::ID} = $parentEntity->id;
			}
		}

		//$values = array_map(function($name){return static::getColumnName($name);},$this->values);
		$values = $v = $this->getValuesForSave();
		//dump($v);
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
			unset($valuesWithoutPK[static::getPrefix().'_'.self::ID]);
			
			dibi::query(
				'INSERT INTO `'.static::getTableName().'`', $values,
				'ON DUPLICATE KEY UPDATE '.static::getPrefix().'_'.self::ID.'=LAST_INSERT_ID('.static::getPrefix().'_'.self::ID.') 
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
		foreach (static::$CHILDREN as $childName=>$childClass) {
			if (isset($this->$childName))
				foreach ($this->$childName as $i=>$childEntity) {
					$childEntity->{static::getPrefix().'_'.self::ID} = $this->id;
					$childEntity->save();
				}
		}
		return TRUE;
	}
	
	protected function afterSave(&$values) {
		
	}
	
	public function delete() {
		if ($this->id) {
			dibi::query(
				'DELETE FROM '.static::getTableName().' WHERE %and',
					array(static::getPrefix().'_'.self::ID=>$this->id),'LIMIT 1'
			);
		}
		// mazání children zajištěno na úrovni databáze
	}
	
	public function copy() {
		$copy = clone $this;
		$copy->id = NULL;
		if (is_array($copy->_parents))
			foreach ($copy->_parents as $parentName=>$parentEntity) {
				if ($parentEntity !== NULL)
					$copy->_parents[$parentName] = $this->_parents[$parentName]->copy();
			}
		if (is_array($copy->_children))
			foreach ($copy->_children as $childName=>$childEntities)
				foreach ($copy->_children[$childName] as $i=>$childEntity) {
					$copy->_children[$childName][$i] = $this->_children[$childName][$i]->copy();
				}
				
		return $copy;
		
	}
	
	public function getValues() {
		$values = array();
		foreach ($this->_values as $name=>$value) {
			$key = $name;
			if (strpos($name, static::getPrefix().'_') === 0) {
				$_name = substr($name, strlen(static::getPrefix())+1);
				if (!static::isColumn($_name))
					$key = $_name; 
			}
			$values[$key] = $value;
		}
		if ($this->_id) {
			$values[self::ID] = $this->_id;
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
		if (strpos($name, '_') === 0) //name starts with undescore
			$_name = substr($name, 1);
		else $_name = FALSE;
		
		if ($name == 'id' || $name == static::getPrefix().'_'.self::ID) {
			return $this->_id;
		} elseif ($_name && self::getColumnName($_name) && array_key_exists(self::getColumnName($_name), $this->_values)) {
			return $this->_values[self::getColumnName($_name)];
		} elseif (method_exists($this, ($m_name = 'get'.ucfirst(Helpers::toCamelCase($name))) )) {//get{Name}
			return $this->{$m_name}();
		} elseif (self::getColumnName($name) && array_key_exists(self::getColumnName($name), $this->_values)) {
			return $this->_values[self::getColumnName($name)];
		} elseif (array_key_exists($name, $this->_parents)) {
			if ( (!$this->_parents[$name] instanceof self || !$this->_parents[$name]->_self_loaded) &&
				 (!isset($this->_loaded[$name]) || !$this->_loaded[$name]) ) //lazy loading
				$this->{'load'.ucfirst($name)}();
			return $this->_parents[$name];
		} elseif ($_name && array_key_exists($_name, $this->_parents)) {
			return $this->_parents[$_name];
		} elseif (array_key_exists($name, $this->_children)) {
			if (!isset($this->_loaded[$name]) || !$this->_loaded[$name]) //lazy loading
				$this->{'load'.ucfirst($name)}();
			return $this->_children[$name];
		} elseif ($_name && array_key_exists($_name, $this->_children)) {
			return $this->_children[$_name];
		} elseif (array_key_exists($name, $this->_aux)) {
			return $this->_aux[$name];
		} elseif (preg_match('/^(.*)_datetime$/', $name, $matches) && isset($this->{$matches[1]})) {
			return new \DateTime($this->{$matches[1]});
		} elseif (static::isCallable( $method = 'get'.ucfirst(Helpers::toCamelCase($name)) )) {//static get{Name}
			//Debug::dump($method);
			return static::$method();
		/*} else {
			return $this->$name;*/
		}
	}
	
	public function __set($name, $value) {
		if ($name == 'values') {
			if (is_array($value) || is_object($value)) {
				$this->_self_loaded = TRUE;
				foreach ($value as $key=>$val) {
					if ($this->__isset($key))
						$this->$key = $val;
				}
				if (is_array($value) && isset($value[static::getPrefix().'_'.self::ID])) {
					$this->id = $value[static::getPrefix().'_'.self::ID];
				}
				if (is_object($value) && isset($value->{static::getPrefix().'_'.self::ID})) {
					$this->id = $value->{static::getPrefix().'_'.self::ID};
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
			$this->_parents[$name] = $value;
		} elseif (($value instanceof RowCollection) && in_array($name, array_keys(static::$CHILDREN))) {
			if ($value->isEmpty() || in_array($value->getClass(), static::$CHILDREN))
				$this->_children[$name] = $value;
			else throw new \Exception('The collection of objects ('.$name.') that have class '.$value->getClass().' not defined in CHILDREN');
		} elseif ($name == 'id' || $name == static::getPrefix().'_'.self::ID) {
			$newId = intval($value) === 0 ? NULL : intval($value);
			if ($this->_id !== $newId && !is_null($this->_id))
				array_map(function($item){return Table::VALUE_MODIFIED;}, $this->_modified);
			
			$this->_id = $newId;
				
		} elseif ($_name = static::getColumnName($name)) {
			if ($this->_values[$_name] !== $value)
				$this->_modified[$_name] = self::VALUE_MODIFIED;
			
			$this->_values[$_name] = $value;
		} else {
			//throw new \Exception('Undefined property '.$name.' (class '.get_class($this).')');
			$this->_aux[$name] = $value;
		}
	}
	
	public function __isset($name) {
		if (
			$name == self::ID ||
			self::getColumnName($name) && array_key_exists(static::getColumnName($name), $this->_values) || 
			array_key_exists($name, $this->_children) ||
			(array_key_exists($name, $this->_parents) && !is_null($this->_parents[$name])) ||
			array_key_exists($name, $this->_aux)
		) {
			return true;
		} else {
			return false;
		}
	}
	
	public function __clone() {
		if (is_array($this->_parents))
			foreach ($this->_parents as $parentName=>$parentEntity) {
				if ($parentEntity !== NULL)
					$this->_parents[$parentName] = clone $this->_parents[$parentName];
			}
		if (is_array($this->_children))
			foreach ($this->_children as $childName=>$childEntities) {
				$this->_children[$childName] = clone $this->_children[$childName];
				foreach ($this->_children[$childName] as $i=>$childEntity) {
					$this->_children[$childName][$i] = clone $this->_children[$childName][$i];
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
			return static::__callStatic($name, $arguments);
		}
	}
	
	public static function __callStatic($name, $arguments) {
		array_unshift($arguments, get_called_class());
		return call_user_func_array(array(__CLASS__.'\Helpers', $name), $arguments);
	}
	
	private static function isCallable($method) {
		return method_exists(get_called_class(), $method) || method_exists(__CLASS__.'\Helpers', $method);
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
			$entity = new $model_class($row->{static::getPrefix().'_'.self::ID});
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
			
			$rows[$row->{static::getPrefix().'_'.self::ID}] = $entity;
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
					static::getPrefix().'_'.self::ID=>'id',
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
						'ON (`'.$alias.'.'.$parentClass::getPrefix().'_'.self::ID.'`=`'.$alias.self::ALIAS_DELIM.$parentName.'.'.$parentClass::getPrefix().'_'.self::ID.'`)';
			}
		/*foreach (static::$CHILDREN as $childClass) {
			if ($childClass != get_called_class())
				$from .= ' LEFT JOIN `' . $childClass::getTableName() . '` USING(`'.static::getPrefix().'_'.self::ID.'`)';
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
	
}
?>