<?php

namespace isqua\Table;


use isqua\Entity;
use isqua\RowCollection;
use dibi;



abstract class Select 
{

	const ALIAS = '$this';
	const ALIAS_DELIM = '->';


	public static function count($class, $where = array()) {
		return (int)dibi::fetchSingle(
			self::getSql($class, array('COUNT(*)%ex'=>'count'), $where, array(), array(), TRUE ) //vždy s parents? co třeba podle where?
		);
	}
	
	public static function getOne_($class, $where = array(), $sort = array(), $limit = array()) {
		return self::getOne($class, $where, $sort, $limit, TRUE);
	}

	public static function getOne($class, $where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		$rows = self::getAll($class, $where, $sort, $limit, $withParents);
		return $rows->getFirst();
	}
	
	public static function getAll_($class, $where = array(), $sort = array(), $limit = array()) {
		return self::getAll($class, $where, $sort, $limit, TRUE);
	}

	public static function getAll($class, $where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		return self::getFromSql(
			$class,
			$withParents,
			self::getSql(
				$class,
				array('*'),
				$where, $sort, $limit, $withParents
			)
		);
	}
	
	public static function getFromSql($class, $withParents) {
		$args = func_get_args();
		unset($args[0]);
		if (is_bool($withParents)) {
			unset($args[1]);
		} else {
			$withParents = FALSE;
		}
		//dibi::test($args);
		$cursor = dibi::fetchAll($args);
		$rows = new RowCollection();
		foreach ($cursor as $row) {
			// handle entity inheritance
			if (isset($row[Entity::ENTITY_COLUMN])) {
				$entity = $class::create($row->{$class::getColumnName(Entity::ID)});
			} else {
				$entity = new $class($row->{$class::getColumnName(Entity::ID)});
			}
			$entity->column_values = $row;
			//$entity->loadChildren();
			if ($withParents) {
				foreach ($entity::getParents() as $parentName=>$parentClass) {
					$parentEntity = new $parentClass();
					$parentEntity->column_values = $row;
					/**
					 * @todo FUJ - lépe!
					 */
					foreach ($parentClass::getParents() as $supParentName=>$supParentClass) {
						$supParentEntity = new $supParentClass();
						$supParentEntity->column_values = $row;
						$parentEntity->{$supParentName} = $supParentEntity; 
					}
					$entity->{$parentName} = $parentEntity; 
				}
				foreach ($entity::getSingles() as $singleName=>$singleClass) {
					$singleEntity = new $singleClass();
					$singleEntity->column_values = $row;
					$entity->{$singleName} = $singleEntity;
				}
			}

			
			$rows[$row->{$class::getColumnName(Entity::ID)}] = $entity;
		}
		return $rows;		
	}
	
	public static function getColumn_($class, $column = 'name', $where = array(), $sort = array(), $limit = array(), $concatNamesInTree = FALSE) {
		return self::getColumn($class, $column, $where, $sort, $limit, $concatNamesInTree, TRUE);
	}
	
	public static function getColumn($class, $column = 'name', $where = array(), $sort = array(), $limit = array(), $concatNamesInTree = FALSE, $withParents = FALSE) {
		
		$cursor = dibi::fetchAll(
			self::getSql(
				$class,
				array(
					$class::getColumnName(Entity::ID)=>'id',
					$class::getColumnName($column)=>'name'
				),
				$where, $sort, $limit, $withParents
			)
		);
		$rows = array();
		foreach ($cursor as $row) {
			if ($class::isSelfReferencing() && ($children = self::getColumn($class, $column, array_merge( $where, array('parent_id'=>$row->_id) ), $sort)) ) {
				
				$rows[$row->name] = 
					array($row->_id => $row->name) +  
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
	private static function getSql($class, $columns = array('*'), $where = array(), $sort = array(), $limit = array(), $withParents = FALSE) {
		//dibi::test(
		return dibi::sql(
			'SELECT %n', $columns,
			' FROM '.self::getFromClause($class, $withParents).
			self::getWhereClause($class, $where, $sort, $limit)
		);
		//return 'SELECT * FROM '.static::getFromClause() . ' LIMIT 1 ';
	}
	
	public static function getFromClause($class, $withParents = FALSE, $alias = self::ALIAS) {
		$from = '`'.$class::getTableName().'` AS `'.$alias.'`';
		if ($withParents) {
			foreach ($class::getParents() as $parentName=>$parentClass) {
				if ($parentClass != $class)
					$from .= ' LEFT JOIN (' . self::getFromClause($parentClass, $withParents, $alias.self::ALIAS_DELIM.$parentName) . ') '. 
						'ON (`'.$alias.'.'.$class::getColumnName($parentName).'`=`'.$alias.self::ALIAS_DELIM.$parentName.'.'.$parentClass::getColumnName(Entity::ID).'`)';
			}

			foreach ($class::getSingles() as $singleName=>$singleClass) {
				if ($singleClass != $class)
					$from .= ' LEFT JOIN (' . self::getFromClause($singleClass, FALSE, $alias.self::ALIAS_DELIM.$singleName) . ') '.
						'ON (`'.$alias.'.'.$class::getColumnName(Entity::ID).'`=`'.$alias.self::ALIAS_DELIM.$singleName.'.'.$class::getForeignKeyName($singleName).'`)';
			}
		}
		return $from;
	}
	
	public static function getWhereClause($class, $where = array(), $sort = array(), $limit = array(), $alias = self::ALIAS) {
		self::replaceKeys($class, $where, $alias);
		self::replaceKeys($class, $sort, $alias);
		
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
	
	public static function replaceKeys($class, &$array, $alias = FALSE) {
		$newArray = array();
		foreach ($array as $key=>$item) { 
			if ($column = $class::getColumnName($key, $alias))
				$newArray[$column] = $item;
			elseif (is_int($key)) //numeric index
				$newArray[$key] = $item;
			else 
				$newArray[$key] = $item; //zkusíme ho nechat na pokoj
		} 
		$array = $newArray;
	}
	
	
} 