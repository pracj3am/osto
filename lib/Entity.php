<?php

namespace osto;

use osto\Table\Helpers;
use dibi;



abstract class Entity implements \ArrayAccess, \IteratorAggregate
{
    const ALL = 'all';
    const PARENT = 'ParentEntity';
    const ENTITY_COLUMN = 'entity';

    const VALUE_NOT_SET = 0;
    const VALUE_NOT_MODIFIED = 1;
    const VALUE_MODIFIED = 2;

    protected static $_REFLECTIONS = array();
    
    private $_id;
    private $_values = array();
    private $_modified = array();
    private $_parents = array();
    private $_children = array();
    private $_singles = array();
    private $_loaded;
    private $_self_loaded;
    private $_aux = array();



    /**
     * Constructor
     * @param int $id primary key value
     */
    public function __construct($id = NULL)
    {
        $this->initialize();
        if (is_array($id)) {
            $this->setColumnValues($id);
        } else {
            $this->id = $id;
        }
    }



    /**
     * Initializes entity internal properties. Called by the constructor
     * @return void
     */
    private function initialize()
    {
        $this->_self_loaded = FALSE;
        
        $r = static::getReflection();
        foreach ($r->columns as $prop=>$column) {
            if ($prop != $r->primaryKey) {
                $this->_modified[$column] = self::VALUE_NOT_SET;
                $this->_values[$column] = NULL;
            }
        }
        foreach ($r->parents as $parentName => $parentClass) {
            $this->_parents[$parentName] = NULL;
            $this->_loaded[$parentName] = FALSE;
        }
        foreach ($r->children as $childName => $childClass) {
            $this->_children[$childName] = new EntityCollection;
            $this->_loaded[$childName] = FALSE;
        }
        foreach ($r->singles as $singleName => $singleClass) {
            $this->_singles[$singleName] = NULL;
            $this->_loaded[$singleName] = FALSE;
        }
    }



    /**
     * Tells wheter it is a standalone entity or an ancestor-part of another standalone entity
     * @return bool
     */
    private function isStandalone()
    {
        return !isset($this[self::ENTITY_COLUMN]) || $this[self::ENTITY_COLUMN] === get_class($this);
    }



    /**
     * Returns entity class name with respect to inheritance issue
     * @return string
     */
    private function getEntityClass()
    {
        return $this->isStandalone() ? get_class($this) : $this[self::ENTITY_COLUMN];
    }



    /**
     * Is data loaded?
     * @return bool
     */
    final public function isLoaded()
    {
        return $this->_self_loaded;
    }



    /**
     * Loads entity data
     * @param int $depth depth of loading recursion
     * @return bool is loading successfull
     */
    final public function load($depth = 0)
    {
        if ($this->_id !== NULL) {
            if (!($this->_self_loaded)) {
                $row = dibi::fetch( static::getSql(array('*'), array(static::getReflection()->primaryKey=>$this->_id)) );
                if ($row) {
                    foreach ($row as $name => $value) {
                        if ($name != static::getReflection()->primaryKeyColumn) {
                            $this->_values[$name] = $value;
                            $this->_modified[$name] = self::VALUE_NOT_MODIFIED;
                        }
                    }
                    $this->_self_loaded = TRUE;
                } else {
                    return FALSE;
                }
            }

            $this->loadParents(self::PARENT);
            if ($depth > 0) {
                $this->loadParents(self::ALL, $depth-1);
                $this->loadSingles(self::ALL, $depth-1);
                $this->loadChildren(self::ALL, $depth-1);
            }

            return TRUE;
        }
        
        return FALSE;
    }



    /**
     * Instantiates parent entities for existing foreign keys ('belongs to' relation) and try to load their data
     * @param array|string $parentNames Array of parent names or self::ALL
     * @param int $depth depth of loading recursion
     */
    final public function loadParents($parentNames = self::ALL, $depth = 0)
    {
        if ($parentNames === self::ALL) {
            $parentNames = array_keys($this->_parents);
        } elseif (!is_array($parentNames)) {
            $parentNames = (array)$parentNames;
        }

        foreach (static::getReflection()->parents as $parentName => $parentClass) {
            if (in_array($parentName, $parentNames) && isset($this[static::getColumnName($parentName)])) {

                if ($parentName === self::PARENT) {
                    $parentEntity = new $parentClass($this[static::getColumnName($parentName)]);
                    $parentEntity->load($depth);
                } else {
                    $parentEntity = $parentClass::findById($this[static::getColumnName($parentName)]);
                }

                $this->_parents[$parentName] = $parentEntity;
                $this->_loaded[$parentName] = $parentEntity->isLoaded();
            }
        }
    }



    /**
     * Instantiates singles entities ('has one' relation) and try to load their data
     * @param array|string $singleNames array of single names or self::ALL
     * @param int $depth depth of loading recursion
     */
    final public function loadSingles($singleNames = self::ALL, $depth = 0)
    {
        if ($this->_id !== NULL) {
            if ($singleNames === self::ALL) {
                $singleNames = array_keys($this->_singles);
            } elseif (!is_array($singleNames)) {
                $singleNames = (array)$singleNames;
            }

            foreach (static::getReflection()->singles as $singleName => $singleClass) {
                if (in_array($singleName, $singleNames)) {
                    $entity = $singleClass::getOne(array(
                                static::getForeignKeyName($singleName) => $this->id
                            ));
                    $entity->load($depth);
                    $this->_singles[$singleName] = $entity;
                    $this->_loaded[$singleName] = $entity->isLoaded();
                }
            }
        }
    }



    final public function loadChildren($childrenNames = array(), $where = array(), $sort = array(), $limit = array(), $withParents = FALSE)
    {
        if ($this->_id) {
            if (empty($childrenNames))
                $childrenNames = array_keys($this->_children);
            foreach (static::getChildren() as $childName => $childClass) {
                if (in_array($childName, $childrenNames)) {
                    if (get_class($this) == $childClass)//load children of the same class
                        $fk = 'parent_id';
                    else
                        $fk = static::getForeignKeyName($childName);
                    $whereTmp = array_merge(
                                    isset($where[$childName]) ? $where[$childName] : array(),
                                    array($fk => (int) $this->_id)
                    );
                    $sortTmp = isset($sort[$childName]) ? $sort[$childName] : array();
                    $limitTmp = isset($limit[$childName]) ? $limit[$childName] : array();
                    $this->$childName = $childClass::getAll($whereTmp, $sortTmp, $limitTmp, $withParents);
                    $this->_loaded[$childName] = TRUE;
                }
            }
        } else
            return NULL;
    }



    public function getParent($root = false)
    {
        if (static::isSelfReferencing() && $this->parent_id) {
            $parent = static::create($this->parent_id);
            $parent->load();
            if ($root)
                while ($parent->parent_id !== NULL) {
                    $parent = static::create($parent->parent_id);
                    $parent->load();
                }
            return $parent;
        }

        return NULL;
    }



    public function getValuesForSave()
    {
        $values = $this->values;
        foreach ($values as $key => $value) {
            if ($key == static::getReflection()->primaryKey)
                continue; // primární klíč vždy potřebujeme

                if (!is_scalar($value) && $value !== NULL)
                unset($values[$key]);
            // ukládáme jen hodnoty, které se změnily
            elseif ($this->_id && $this->_modified[static::getColumnName($key)] !== self::VALUE_MODIFIED)
                $values[$key] = '`' . static::getColumnName($key) . '`';
            elseif ($value === NULL && !static::isNullColumn($key))
                unset($values[$key]);
        }
        return $values;
    }



    final public function save()
    {
        //uložíme rodiče
        foreach ($this->_parents as $parentName => $parentEntity) {
            if ($parentEntity instanceof self) {
                if ($parentName === self::PARENT) {
                    $parentEntity[self::ENTITY_COLUMN] = get_class($this);
                }
                $parentEntity->save();
                $this[static::getColumnName($parentName)] = $parentEntity->_id;
            }
        }

        $values = $v = $this->getValuesForSave();
        static::replaceKeys($values);
        foreach ($values as $key => $value) {
            if (strpos($value, '`') === 0) {
                unset($values[$key]);
                $values[$key . '%n'] = str_replace('`', '', $value); //%n modifier for dibi
            }
        }
        if ($values) {
            $valuesWithoutPK = $values;
            unset($valuesWithoutPK[static::getReflection()->primaryKeyColumn]);

            dibi::query(
                'INSERT INTO `' . static::getTableName() . '`', $values,
                'ON DUPLICATE KEY UPDATE ' . static::getReflection()->primaryKeyColumn . '=LAST_INSERT_ID(' . static::getReflection()->primaryKeyColumn . ')
                 %if', $valuesWithoutPK, ', %a', $valuesWithoutPK, '%end'
            );
            $this->afterSave($v);
            //dibi::dump();//die();
            if (!$this->_id) {
                $id = dibi::insertId();
                if ($this->_id && $this->_id != $id)
                    throw new Exception('ID changed!');
                $this->_id = (int) $id;
            }
        }

        //save singles
        foreach ($this->_singles as $singleName => $single) {
            if ($single instanceof self) {
                $single[static::getForeignKeyName($singleName)] = $this->_id;
                $single->save();
            }
        }

        //save children
        foreach ($this->_children as $childName => $children) {
            foreach ($children as $i => $childEntity) {
                $childEntity[static::getForeignKeyName($childName)] = $this->_id;
                $childEntity->save();
            }
        }
        return TRUE;
    }



    protected function afterSave(&$values)
    {

    }



    final public function delete()
    {
        if ($this->_id) {
            dibi::query(
                'DELETE FROM ' . static::getTableName() . ' WHERE %and',
                array(static::getReflection()->primaryKey => $this->_id), 'LIMIT 1'
            );
        }
        // mazání children zajištěno na úrovni databáze
    }



    public function copy()
    {
        $copy = clone $this;
        $copy->_id = NULL;
        if (is_array($copy->_parents))
            foreach ($copy->_parents as $parentName => $parentEntity) {
                if ($parentEntity !== NULL)
                    $copy->_parents[$parentName] = $this->_parents[$parentName]->copy();
            }
        if (is_array($copy->_children))
            foreach ($copy->_children as $childName => $childEntities)
                foreach ($copy->_children[$childName] as $i => $childEntity) {
                    $copy->_children[$childName][$i] = $this->_children[$childName][$i]->copy();
                }
        if (is_array($copy->_singles))
            foreach ($copy->_singles as $singleName => $singleEntity) {
                if ($singleEntity !== NULL)
                    $copy->_singles[$singleName] = $this->_singles[$singleName]->copy();
            }

        return $copy;
    }



    public function getValues()
    {
        $values = array();
        foreach ($this->columns as $prop => $name) {
            if ($prop == static::getReflection()->primaryKey) {
                if ($this->_id)
                    $values[static::getReflection()->primaryKey] = $this->_id;
            } else {
                $values[$prop] = $this->_values[$name];
            }
        }

        foreach ($this->_parents as $parentName => $parentEntity) {
            unset($values[$parentName]);
            $values[static::getColumnName($parentName)] = $this->_values[static::getColumnName($parentName)];
            if ($parentName === self::PARENT && !$parentEntity instanceof self) {
                $parentEntity = $this->{self::PARENT};
            }
            if ($parentEntity instanceof self)
                $values[$parentName] = $parentEntity->values;
        }
        foreach ($this->_children as $childName => $children) {
            foreach ($children as $i => $childEntity)
                $values[$childName][$i] = $childEntity->values;
        }
        foreach ($this->_singles as $singleName => $single) {
            if ($single instanceof self)
                $values[$singleName] = $single->values;
        }
        return $values;
    }



    public function setValues($value, $isColumns = FALSE)
    {
        if (is_array($value) || is_object($value)) {
            $this->_self_loaded = TRUE;
            foreach ($value as $key => $val) {
                if ($this->__isset($key))
                    if ($isColumns)
                        $this[$key] = $val;
                    else
                        $this->$key = $val;
            }
            if (is_array($value) && isset($value[static::getReflection()->primaryKeyColumn])) {
                $this->_id = (int) $value[static::getReflection()->primaryKeyColumn];
            }
            if (is_object($value) && isset($value->{static::getReflection()->primaryKeyColumn})) {
                $this->_id = (int) $value->{static::getReflection()->primaryKeyColumn};
            }
            foreach ($this->_parents as $parentName => $parentEntity) {
                if (is_array($value) && isset($value[$parentName]) || isset($value->$parentName)) {
                    if (!isset($this->_parents[$parentName])) {
                        $parentClass = $this->parents[$parentName];
                        $this->_parents[$parentName] = new $parentClass();
                    }
                    $this->_parents[$parentName]->values = is_array($value) ? $value[$parentName] : $value->$parentName;
                }
            }
            foreach ($this->_children as $childName => $childEntities) {
                if (is_array($value) && isset($value[$childName]) && is_array($childArray = $value[$childName]) || isset($value->$childName) && is_array($childArray = $value->$childName)) {
                    foreach ($childArray as $i => $childValues) {
                        if (!isset($childEntities[$i])) {
                            $children = static::getChildren();
                            $childClass = $children[$childName];
                            $childEntities[$i] = new $childClass();
                        }
                        $childEntities[$i]->values = $childValues;
                    }
                    if ($childEntities)
                        $this->_children[$childName] = $childEntities;
                }
            }
            foreach ($this->_singles as $singleName => $singleEntity) {
                if (is_array($value) && isset($value[$singleName]) || isset($value->$singleName)) {
                    if (!isset($this->_singles[$singleName])) {
                        $singleClass = $this->singles[$singleName];
                        $this->_singles[$singleName] = new $singleClass();
                    }
                    $this->_singles[$singleName]->values = is_array($value) ? $value[$singleName] : $value->$singleName;
                }
            }
        }
    }



    final public function setColumnValues($values)
    {
        $this->setValues($values, TRUE);
    }



    private static function replaceKeys(&$array, $alias = FALSE)
    {
        return Table\Select::replaceKeys(get_called_class(), $array, $alias);
    }



    public static function getReflection()
    {
        if (!isset(self::$_REFLECTIONS[get_called_class()]))
            self::$_REFLECTIONS[get_called_class()] = Reflection\EntityReflection::create(get_called_class());
        return self::$_REFLECTIONS[get_called_class()];
    }



    public function __get($name)
    {
        if (strpos($name, '_') === 0) //name starts with undescore
            $_name = substr($name, 1);
        else
            $_name = FALSE;

        if ($name==static::getReflection()->primaryKey) {
            if (array_key_exists(self::PARENT, $this->_parents)) {
                return $this->{self::PARENT}->id;
            }
            return $this->_id;
        } elseif ($_name == 'id' || ($name==static::getReflection()->primaryKeyColumn)) {
            return $this->_id;
        } elseif (array_key_exists($name = trim($name, '0'), $this->_parents)) {
            if ((!$this->_parents[$name] instanceof self /* || !$this->_parents[$name]->_self_loaded */) &&
                    (!isset($this->_loaded[$name]) || !$this->_loaded[$name])) //lazy loading
                $this->{'load' . ucfirst($name)} ();
            if (!$this->_parents[$name] instanceof self) {
                $parentClass = $this->parents[$name];
                $this->_parents[$name] = new $parentClass;
            }
            return $this->_parents[$name];
        } elseif ($_name && array_key_exists($_name, $this->_parents)) {
            return $this->_parents[$_name];
        } elseif (array_key_exists($name = trim($name, '0'), $this->_children)) {
            if ($this->_children[$name]->isEmpty() && (!isset($this->_loaded[$name]) || !$this->_loaded[$name])) //lazy loading
                $this->{'load' . ucfirst($name)} ();
            return $this->_children[$name];
        } elseif ($_name && array_key_exists($_name, $this->_children)) {
            return $this->_children[$_name];
        } elseif (array_key_exists($name = trim($name, '0'), $this->_singles)) {
            if ((!$this->_singles[$name] instanceof self /* || !$this->_parents[$name]->_self_loaded */) &&
                    (!isset($this->_loaded[$name]) || !$this->_loaded[$name])) //lazy loading
                $this->{'load' . ucfirst($name)} ();
            if (!$this->_singles[$name] instanceof self) {
                $singleClass = $this->singles[$name];
                $this->_singles[$name] = new $singleClass;
            }
            return $this->_singles[$name];
        } elseif ($_name && array_key_exists($_name, $this->_singles)) {
            return $this->_singles[$_name];
        } elseif ($_name && self::getColumnName($_name) && array_key_exists(self::getColumnName($_name), $this->_values)) {
            return $this->_values[self::getColumnName($_name)];
        } elseif (method_exists($this, ($m_name = Helpers::getter($name)))) {//get{Name}
            return $this->{$m_name}();
        } elseif (self::getColumnName($name) && array_key_exists(self::getColumnName($name), $this->_values)) {
            return $this->_values[self::getColumnName($name)];
        } elseif (array_key_exists($name, $this->_aux)) {
            return $this->_aux[$name];
        } elseif (preg_match('/^(.*)_datetime$/', $name, $matches) && isset($this->{$matches[1]})) {
            return new \DateTime($this->{$matches[1]});
        } elseif (static::isCallable($method = Helpers::getter($name))) {//static get{Name}
            //Debug::dump($method);
            return static::$method();
            /* } else {
              return $this->$name; */
        } elseif (array_key_exists(self::PARENT, $this->_parents)) {
            return $this->{self::PARENT}->$name;
        }
    }



    public function __set($name, $value)
    {
        if (strpos($name, '_') === 0) //name starts with undescore
            $_name = substr($name, 1);
        else
            $_name = FALSE;

        if (is_object($value) && in_array(get_class($value), $this->parents)) {
            $this->_parents[trim($name, '0')] = $value;
        } elseif (is_object($value) && in_array(get_class($value), $this->singles)) {
            $this->_singles[trim($name, '0')] = $value;
        } elseif (($value instanceof RowCollection) && array_key_exists($name = trim($name, '0'), $this->_children)) {
            if ($value->isEmpty() || in_array($value->getClass(), static::getChildren()))
                $this->_children[$name] = $value;
            else
                throw new \Exception('The collection of objects (' . $name . ') that have class ' . $value->getClass() . ' not defined in CHILDREN');
        } elseif (($name==static::getReflection()->primaryKey) || ($name==static::getReflection()->primaryKeyColumn)) {
            $newId = \intval($value) === 0 ? NULL : \intval($value);
            if ($this->_id !== $newId && $this->_id !== NULL)
                array_map(function($item) {
                            return Entity::VALUE_MODIFIED;
                        }, $this->_modified);

            $this->_id = $newId;
        } elseif ($_name && self::getColumnName($_name) && array_key_exists(self::getColumnName($_name), $this->_values)) {
            $cn = self::getColumnName($_name);
            if ($this->_values[$cn] !== $value)
                $this->_modified[$cn] = self::VALUE_MODIFIED;

            $this->_values[$cn] = $value;
        } elseif (method_exists($this, ($m_name = Helpers::setter($name)))) {//set{Name}
            return $this->{$m_name}($value);
        } elseif (self::getColumnName($name) && array_key_exists(self::getColumnName($name), $this->_values)) {
            $cn = self::getColumnName($name);
            if ($this->_values[$cn] !== $value)
                $this->_modified[$cn] = self::VALUE_MODIFIED;

            $this->_values[$cn] = $value;
        } elseif (array_key_exists(self::PARENT, $this->_parents)) {
            $this->{self::PARENT}->$name = $value;
        } else {
            //throw new \Exception('Undefined property '.$name.' (class '.get_class($this).')');
            $this->_aux[$name] = $value;
        }
    }



    public function __isset($name)
    {
        if (
                $name == 'id' || $name == static::getReflection()->primaryKey ||
                method_exists($this, Helpers::getter($name)) ||
                static::getColumnName($name) && array_key_exists(static::getColumnName($name), $this->_values) ||
                array_key_exists($name, $this->_children) ||
                (array_key_exists($name, $this->_parents) && $this->_parents[$name] !== NULL) ||
                (array_key_exists($name, $this->_singles) && $this->_singles[$name] !== NULL) ||
                array_key_exists($name, $this->_aux)
        ) {
            return true;
        } else {
            return false;
        }
    }



    public function __clone()
    {
        if (is_array($this->_parents))
            foreach ($this->_parents as $parentName => $parentEntity) {
                if ($parentEntity !== NULL)
                    $this->_parents[$parentName] = clone $this->_parents[$parentName];
            }
        if (is_array($this->_children))
            foreach ($this->_children as $childName => $childEntities) {
                $this->_children[$childName] = clone $this->_children[$childName];
                foreach ($this->_children[$childName] as $i => $childEntity) {
                    $this->_children[$childName][$i] = clone $this->_children[$childName][$i];
                }
            }
        if (is_array($this->_singles))
            foreach ($this->_singles as $singleName => $singleEntity) {
                if ($singleEntity !== NULL)
                    $this->_singles[$singleName] = clone $this->_singles[$singleName];
            }
    }



    public function __call($name, $arguments)
    {
        if (strpos($name, 'load') === 0) {//load{Parent} or load{Single} or load{Children}
            $varName = strtolower(substr($name, 4, 1)) . substr($name, 5);
            $VarName = ucfirst($varName);
            if (($a = array_key_exists($varName, $this->parents)) || array_key_exists($VarName, $this->parents)) {
                $parentName = $a ? $varName : $VarName;
                $withChildren = isset($arguments[0]) && $arguments[0];
                return $this->loadParents(array($parentName), $withChildren);
            } elseif (($a = array_key_exists($varName, $this->singles)) || array_key_exists($VarName, $this->singles)) {
                $singleName = $a ? $varName : $VarName;
                $withChildren = isset($arguments[0]) && $arguments[0];
                return $this->loadSingles(array($singleName), $withChildren);
            } elseif (($a = array_key_exists($varName, static::getChildren())) || array_key_exists($VarName, static::getChildren())) {
                $childName = $a ? $varName : $VarName;
                $where = isset($arguments[0]) ? array($childName => $arguments[0]) : array();
                $sort = isset($arguments[1]) ? array($childName => $arguments[1]) : array();
                $limit = isset($arguments[2]) ? array($childName => $arguments[2]) : array();
                $withParents = isset($arguments[3]) && $arguments[3];
                return $this->loadChildren(array($childName), $where, $sort, $limit, $withParents);
            }
        } else {
            return static::__callStatic($name, $arguments);
        }
    }



    public static function __callStatic($name, $arguments)
    {
        if (method_exists(static::getReflection(), $name)) {
            return call_user_func_array(array(static::getReflection(), $name), $arguments);
        }

        array_unshift($arguments, get_called_class());
        return call_user_func_array(array(__NAMESPACE__ . '\Table\Select', $name), $arguments);
    }



    private static function isCallable($method)
    {
        return method_exists(get_called_class(), $method) || method_exists(static::getReflection(), $method);
    }



    final public function offsetSet($name, $value)
    {
        if ($name === FALSE)
            throw new \Exception;
        if (array_key_exists($name, $this->_values) || $name === self::ENTITY_COLUMN)
            $this->_values[$name] = $value;
    }



    final public function offsetGet($name)
    {
        return $this->_values[$name];
    }



    final public function offsetExists($name)
    {
        return array_key_exists($name, $this->_values);
    }



    final public function offsetUnset($name)
    {
        if (array_key_exists($name, $this->_values))
            unset($this->_values[$name]);
    }



    final public function getIterator()
    {
        return new \ArrayIterator($this->_values);
    }

}
?>