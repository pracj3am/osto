<?php

namespace osto;

use dibi;



abstract class Entity implements \ArrayAccess, \IteratorAggregate
{
    const ALL = 'all';
    const PARENT = 'ParentEntity';
    const ENTITY_COLUMN = 'entity';

    const VALUE_NOT_SET = 0;
    const VALUE_SET = 1;
    const VALUE_MODIFIED = 2;

    protected static $_REFLECTIONS = array();
    
    private $_id;
    private $_values = array();
    private $_properties = array();
    private $_modified = array();
    private $_parents = array();
    private $_children = array();
    private $_singles = array();
    private $_loaded;
    private $_self_loaded;
    /**
     *
     * @var Reflection\EntityReflection
     */
    private $_reflection;


    /**
     * Constructor
     * @param int|array $id primary key value or array of values
     */
    public function __construct($id = NULL)
    {
        $this->initialize();
        if (\is_array($id)) {
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

        $this->_reflection = static::getReflection();
        foreach ($this->_reflection->columns as $prop=>$column) {
            if ($prop != $r->primaryKey) {
                $this->_modified[$column] = self::VALUE_NOT_SET;
                $this->_properties[$prop] = $column;
                $this->_values[$column] = NULL;
            }
        }
        foreach ($this->_reflection->parents as $parentName => $parentClass) {
            $this->_parents[$parentName] = NULL;
            $this->_loaded[$parentName] = FALSE;
        }
        foreach ($this->_reflection->children as $childName => $childClass) {
            $this->_children[$childName] = NULL;
            $this->_loaded[$childName] = FALSE;
        }
        foreach ($this->_reflection->singles as $singleName => $singleClass) {
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
        return !isset($this[self::ENTITY_COLUMN]) || $this[self::ENTITY_COLUMN] === \get_class($this);
    }



    /**
     * Returns entity class name with respect to inheritance issue
     * @return string
     */
    private function getEntityClass()
    {
        return $this->isStandalone() ? \get_class($this) : $this[self::ENTITY_COLUMN];
    }



    /******************** MAGIC METHODS *******************/



    public function __get($name)
    {
        //name starts with underscore -> no magic functionality
        if (\strpos($name, '_') === 0) {
            $_name = \substr($name, 1);
        } else {
            $_name = FALSE;
        }

        /***** primary key *****/

        if ($name == 'id' || $name == $this->_reflection->primaryKey) {
            if (\array_key_exists(self::PARENT, $this->_parents)) {
                return $this->parents[self::PARENT]->id;
            }
            return $this->_id;
        } 
        
        if ($name == $this->_reflection->primaryKeyColumn) {
            return $this->_id;
        }

        /***** parents *****/

        if ($_name && \array_key_exists($_name, $this->_parents)) {
            return $this->_parents[$_name];
        }

        // lazy loading
        if (\array_key_exists($name, $this->_parents)) {
            if (!($this->_parents[$name] instanceof self)) {
                $this->loadParents($name);
            }
            return $this->_parents[$name];
        }

        /***** singles *****/

        if ($_name && \array_key_exists($_name, $this->_singles)) {
            return $this->_singles[$_name];
        }

        //lazy loading
        if (\array_key_exists($name, $this->_singles)) {
            if (!($this->_singles[$name] instanceof self)) {
                $this->loadSingles($name);
            }
            return $this->_singles[$name];
        }
        
        /***** children *****/

        if ($_name && \array_key_exists($_name, $this->_children)) {
            return $this->_children[$_name];
        }

        // lazy loading
        if (\array_key_exists($name, $this->_children)) {
            if (!($this->_children[$name] instanceof Table)) {
                $this->loadChildren($name);
            }
            return $this->_children[$name];
        }

        /***** values *****/

        if ($_name && \array_key_exists($_name, $this->_properties)) {
            return $this->_values[$_name];
        }

        if (\method_exists($this, ($m_name = Helpers::getter($name)))) {//get{Name}
            return $this->{$m_name}();
        }

        if (\array_key_exists($name, $this->_properties)) {
            return $this->_values[$name];
        }

        /***** inheritance *****/

        if (\array_key_exists(self::PARENT, $this->_parents)) {
            return $this->_parents[self::PARENT]->$name;
        }

        throw new Exception("Undeclared property $name.");
    }



    public function __isset($name)
    {
        if (
                $name == 'id' || $name == $this->_reflection->primaryKey || $name == $this->_reflection->primaryKeyColumn ||
                \method_exists($this, Helpers::getter($name)) ||
                \array_key_exists($name, $this->_properties) ||
                \array_key_exists($name, $this->_children) && $this->_children[$name] !== NULL ||
                \array_key_exists($name, $this->_parents) && $this->_parents[$name] !== NULL ||
                \array_key_exists($name, $this->_singles) && $this->_singles[$name] !== NULL ||
                \array_key_exists(self::PARENT,$this->_parents) && isset($this->_parents[self::PARENT]->$name)
        ) {
            return TRUE;
        }

        return FALSE;
    }



    public function __set($name, $value)
    {
        //name starts with underscore -> no magic functionality
        if (\strpos($name, '_') === 0) {
            $_name = \substr($name, 1);
        } else {
            $_name = FALSE;
        }

        //primary key
        if ($name == 'id' || $name == $this->_reflection->primaryKey || $name == $this->_reflection->primaryKeyColumn) {
            $value === NULL or \settype($value, $this->_reflection->types[$this->_reflection->primaryKey]);
            $newId = $value === 0 ? NULL : $value;
            if ($this->_id !== $newId && $this->_id !== NULL) {
                $this->_modified = array_fill_keys(array_keys($this->_values), self::VALUE_MODIFIED);
            }
            $this->_id = $newId;
            return;
        }

        //parents
        if (\array_key_exists($name, $this->_parents) && is_object($value) && get_class($value) == $this->_reflection->parents[$name]) {
            $this->_parents[$name] = $value;
            return;
        }

        //singles
        if (\array_key_exists($name, $this->_singles) && is_object($value) && get_class($value) == $this->_reflection->singles[$name]) {
            $this->_parents[$name] = $value;
            return;
        }

        //children
        if (\array_key_exists($name, $this->_children) && is_object($value) && $value instanceof \IDataSource) {
            $this->_children[$name] = $value;
            return;
        }

        /***** values *****/

        if ($_name && \array_key_exists($_name, $this->_properties)) {
            return $this->_setValue($name, $value);
        }

        if (\method_exists($this, ($m_name = Helpers::setter($name)))) {//set{Name}
            return $this->{$m_name}($value);
        }

        if (\array_key_exists($_name, $this->_properties)) {
            return $this->_setValue($name, $value);
        }

        //inheritance
        if (\array_key_exists(self::PARENT, $this->_parents)) {
            $this->_parents[self::PARENT]->$name = $value;
            return;
        }

        throw new Exception("Undeclared property $name.");
    }



    /**
     * Internal method to set atributte value incl. type-casting and modification state flag
     * @param string $name
     * @param mixed $value
     */
    private function _setValue($name, $value)
    {
        $cn = $this->_properties[$name];
        $value === NULL or \settype($value, $this->_reflection->types[$name]);
        if ($this->_modified[$cn] == self::VALUE_NOT_SET) {
            $this->_modified[$cn] = self::VALUE_SET;
        } elseif ($this->_modified[$cn] == self::VALUE_SET && ($value != $this->_values[$cn])) {
            $this->_modified[$cn] = self::VALUE_MODIFIED;
        }

        $this->_values[$cn] = $value;
    }



    public function __call($name, $arguments)
    {
        //load{Parent} or load{Single} or load{Children}
        if (strpos($name, 'load') === 0) {
            $VarName = substr($name, 4);
            $varName = lcfirst($VarName);
            $depth = isset($arguments[0]) && $arguments[0];

            if (($a = array_key_exists($varName, $this->_parents)) || array_key_exists($VarName, $this->_parents)) {
                $parentName = $a ? $varName : $VarName;
                return $this->loadParents(array($parentName), $depth);
            }

            if (($a = array_key_exists($varName, $this->_singles)) || array_key_exists($VarName, $this->_singles)) {
                $singleName = $a ? $varName : $VarName;
                return $this->loadSingles(array($singleName), $depth);
            }

            if (($a = array_key_exists($varName, $this->_children)) || array_key_exists($VarName, $this->_children)) {
                $childName = $a ? $varName : $VarName;
                return $this->loadChildren(array($childName), $depth);
            }
        }

        return static::__callStatic($name, $arguments);
    }



    public static function __callStatic($name, $arguments)
    {
        array_unshift($arguments, new Table(get_called_class()));
        return call_user_func_array(array(__NAMESPACE__ . '\Table\Helpers', $name), $arguments);
    }



    /****************** COPYING *******************/



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



    /**
     * Copies entity to new instance
     * @return Entity new instance
     */
    public function copy()
    {
        $copy = clone $this;
        $copy->_id = NULL;
        foreach ($copy->_parents as $parentName => $parentEntity) {
            if ($parentEntity instanceof self) {
                $copy->_parents[$parentName] = $this->_parents[$parentName]->copy();
            }
        }
        foreach ($copy->_singles as $singleName => $singleEntity) {
            if ($singleEntity instanceof self) {
                $copy->_singles[$singleName] = $this->_singles[$singleName]->copy();
            }
        }
        foreach ($copy->_children as $childName => $childEntities) {
            if ($childEntities instanceof \IDataSource) {
                foreach ($copy->_children[$childName] as $i => $childEntity) {
                    $copy->_children[$childName][$i] = $this->_children[$childName][$i]->copy();
                }
            }
        }

        return $copy;
    }



    /******************** LOADING ********************/



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
                $table = static::getTable();
                $row = $table->where($table->id->eq($this->_id))->fetch();
                if ($row) {
                    $this->_id = $row->id;
                    foreach ($row as $name => $value) {
                        $this->_values[$name] = $value;
                        $this->_modified[$name] = self::VALUE_NOT_MODIFIED;
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
            $parentNames = \array_keys($this->_parents);
        } elseif (!\is_array($parentNames)) {
            $parentNames = (array)$parentNames;
        }

        foreach ($this->_reflection->parents as $parentName => $parentClass) {
            if (in_array($parentName, $parentNames) && isset($this[$this->_reflection->getColumnName($parentName)])) {

                if ($parentName === self::PARENT) {
                    $parentEntity = new $parentClass($this[$this->_reflection->getColumnName($parentName)]);
                    $parentEntity->load($depth);
                } else {
                    $parentEntity = $parentClass::find($this[$this->_reflection->getColumnName($parentName)]) or
                            $parentEntity = new $parentClass;
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
                $singleNames = \array_keys($this->_singles);
            } elseif (!\is_array($singleNames)) {
                $singleNames = (array)$singleNames;
            }

            foreach ($this->_reflection->singles as $singleName => $singleClass) {
                if (in_array($singleName, $singleNames)) {
                    $fkColumn = $singleClass::getTable()->{$this->_reflection->getForeignKeyName($singleName)};
                    $entity = $singleClass::findOne($fkColumn->eq($this->id)) or
                            $entity = new $singleClass;
                    $entity->load($depth);
                    $this->_singles[$singleName] = $entity;
                    $this->_loaded[$singleName] = $entity->isLoaded();
                }
            }
        }
    }



    /**
     * Loads children as an instance of Table
     * @param array|string $childrenNames array of children names or self::ALL
     */
    final public function loadChildren($childrenNames = self::ALL, $depth = 0)
    {
        if ($this->_id !== NULL) {
            if ($childrenNames === self::ALL) {
                $childrenNames = \array_keys($this->_children);
            } elseif (!\is_array($childrenNames)) {
                $childrenNames = (array) $childrenNames;
            }

            foreach ($this->_reflection->children as $childName => $childClass) {
                if (in_array($childName, $childrenNames)) {
                    if (get_class($this) == $childClass) {//load children of the same class
                        $fk = 'parent_id';
                    } else {
                        $fk = $this->_reflection->getForeignKeyName($childName);
                    }
                    $fkColumn = $childClass::getTable()->$fk;
                    $this->$childName = $childClass::getTable()->where($fkColumn->eq($this->id));
                    $this->_loaded[$childName] = TRUE;
                }
            }
        }
    }



    /****************** DATA MANIPULATION *******************/



    public function getValuesForSave()
    {
        $values = $this->values;
        foreach ($values as $key => $value) {
            if ($key == $this->_reflection->primaryKey)
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
            unset($valuesWithoutPK[$this->_reflection->primaryKeyColumn]);

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


    /****************** VALUES *******************/



    final public function setColumnValues($values)
    {
        $this->setValues($values, TRUE);
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



    /****************** STATIC METHODS *******************/



    private static function replaceKeys(&$array, $alias = FALSE)
    {
        return Table\Select::replaceKeys(get_called_class(), $array, $alias);
    }



    /**
     *
     * @return Reflection\EntityReflection
     */
    public static function getReflection()
    {
        if (!isset(self::$_REFLECTIONS[get_called_class()]))
            self::$_REFLECTIONS[get_called_class()] = Reflection\EntityReflection::create(get_called_class());
        return self::$_REFLECTIONS[get_called_class()];
    }



    /**
     *
     * @return Table
     */
    public static function getTable()
    {
        return new Table(\get_called_class());
    }


    private static function isCallable($method)
    {
        return method_exists(get_called_class(), $method);
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



    /****************** IMPLEMENTATION OF INTERFACES *******************/




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