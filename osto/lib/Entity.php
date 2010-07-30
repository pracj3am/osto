<?php

namespace osto;

use dibi;



abstract class Entity implements \ArrayAccess, \IteratorAggregate
{

    const ALL = 'all';
    const EXTENDED = 'extendedEntity';

    /**#@+
     * Value states
     */
    const VALUE_NOT_SET = 0;
    const VALUE_SET = 1;
    const VALUE_MODIFIED = 2;
    /**#@- */

    /**
     * Array of entity references
     * @var array
     */
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
    private $_entityClass;

    /**
     * Reference of entity reflection
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
     * Factory for standalone entity
     *  - it looks for standalone entity in descendant entity classes until it finds one
     * @param array $values
     * @return Entity
     */
    public static function createStandalone(array $values)
    {
        $class = \get_called_class();
        $entityColumn = static::getReflection()->entityColumn;
        if (!isset($values[$entityColumn]) || $class === $values[$entityColumn]) {
            return new $class($values);
        }

        $sClass = $values[$entityColumn];
        $fkColumn = $sClass::getTable()->{self::EXTENDED};
        return $sClass::getTable()->where($fkColumn->eq($values[static::getReflection()->primaryKeyColumn]))->fetch();
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
            if ($prop != $this->_reflection->primaryKey) {
                $this->_modified[$column] = self::VALUE_NOT_SET;
                $this->_properties[$prop] = $column;
                $this->_values[$column] = NULL;
            }
        }

        foreach ($this->_reflection->parents as $parentName => $parentClass) {
            $this->_parents[$parentName] = NULL;
            $this->_loaded[$parentName] = FALSE;
            unset($this->_properties[$parentName]);
        }
        foreach ($this->_reflection->children as $childName => $childClass) {
            $this->_children[$childName] = NULL;
            $this->_loaded[$childName] = FALSE;
        }
        foreach ($this->_reflection->singles as $singleName => $singleClass) {
            $this->_singles[$singleName] = NULL;
            $this->_loaded[$singleName] = FALSE;
        }

        if ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED] = new $this->_reflection->parentEntity;
            $this->_parents[self::EXTENDED]->setEntityClass(\get_class($this));
        }
    }



    /**
     * Tells wheter it is a standalone entity or an ancestor-part of another standalone entity
     * @return bool
     */
    private function isStandalone()
    {
        return !isset($this->_entityClass) || $this->_entityClass === \get_class($this);
    }



    /**
     * Returns entity class name with respect to inheritance issue
     * @return string
     */
    public function getEntityClass()
    {
        return $this->isStandalone() ? \get_class($this) : $this->_entityClass;
    }



    /**
     * Sets entity class name
     * @param string $entityClass
     */
    public function setEntityClass($entityClass)
    {
        $this->_entityClass = $entityClass;
    }



    /******************** MAGIC METHODS *******************/



    /**
     * Should not by called directly
     * @param string $name
     * @return mixed
     */
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
            if (\array_key_exists(self::EXTENDED, $this->_parents)) {
                return $this->parents[self::EXTENDED]->id;
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
                try {
                    $this->loadParents($name);
                } catch (\Exception $e) {
                    $this->_parents[$name] = new $this->_reflection->parents[$name];
                }
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
                try {
                    $this->loadSingles($name);
                } catch (\Exception $e) {
                    $this->_singles[$name] = new $this->_reflection->singles[$name];
                }
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
            if (!($this->_children[$name] instanceof \IDataSource)) {
                try {
                    $this->loadChildren($name);
                } catch (\Exception $e) {
                    $this->_children[$name] = new DataSource\ArraySource;
                }
            }
            return $this->_children[$name];
        }

        /***** values *****/

        if ($_name && \array_key_exists($_name, $this->_properties)) {
            return $this->_values[$this->_properties[$_name]];
        }

        if (\method_exists($this, ($m_name = Helpers::getter($name)))) {//get{Name}
            return $this->{$m_name}();
        }

        if (\array_key_exists($name, $this->_properties)) {
            return $this->_values[$this->_properties[$name]];
        }

        /***** inheritance *****/

        if (\array_key_exists(self::EXTENDED, $this->_parents)) {
            return $this->{self::EXTENDED}->$name;
        }

        throw new Exception("Undeclared property $name.");
    }



    /**
     * Should not by called directly
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (
                $name == 'id' || $name == $this->_reflection->primaryKey || $name == $this->_reflection->primaryKeyColumn ||
                \method_exists($this, Helpers::getter($name)) ||
                \array_key_exists($name, $this->_properties) ||
                \array_key_exists($name, $this->_children) && $this->_children[$name] !== NULL ||
                \array_key_exists($name, $this->_parents) && $this->_parents[$name] !== NULL ||
                \array_key_exists($name, $this->_singles) && $this->_singles[$name] !== NULL ||
                \array_key_exists(self::EXTENDED,$this->_parents) && isset($this->{self::EXTENDED}->$name)
        ) {
            return TRUE;
        }

        return FALSE;
    }



    /**
     * Should not by called directly
     * @param string $name
     * @param mixed $value
     * @return void
     */
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
            $value === NULL or \settype($value, $this->_reflection->types[$this->_reflection->primaryKeyColumn]);
            $newId = $value === 0 ? NULL : $value;
            if ($this->_id !== $newId && $this->_id !== NULL) {
                $this->_modified = array_fill_keys(array_keys($this->_values), self::VALUE_MODIFIED);
                \trigger_error("OSTO: Id of entity '".\get_class($this)."' has changed.", E_USER_WARNING);
            }
            $this->_id = $newId;
            return;
        }

        //parents
        if (\array_key_exists($name, $this->_parents)) {
            if (\is_object($value) && \get_class($value) == $this->_reflection->parents[$name]) {
                $this->_parents[$name] = $value;
                return;
            }
            throw new Exception("Property $name must be entity of class ".$this->_reflection->parents[$name]);
        }

        //singles
        if (\array_key_exists($name, $this->_singles)) {
            if (\is_object($value) && \get_class($value) == $this->_reflection->singles[$name]) {
                $this->_singles[$name] = $value;
                return;
            }
            throw new Exception("Property $name must be entity of class ".$this->_reflection->singles[$name]);
        }

        //children
        if (\array_key_exists($name, $this->_children)) {
            if (\is_object($value) && $value instanceof \IDataSource) {
                $this->_children[$name] = $value;
                return;
            }
            throw new Exception("Property $name must implement interface IDataSource.");
        }

        /***** values *****/

        if ($_name && \array_key_exists($_name, $this->_properties)) {
            return $this->_setValue($this->_properties[$_name], $value);
        }

        if (\method_exists($this, ($m_name = Helpers::setter($name)))) {//set{Name}
            return $this->{$m_name}($value);
        }

        if (\array_key_exists($name, $this->_properties)) {
            return $this->_setValue($this->_properties[$name], $value);
        }

        //inheritance
        if (\array_key_exists(self::EXTENDED, $this->_parents)) {
            $this->{self::EXTENDED}->$name = $value;
            return;
        }

        throw new Exception("Undeclared property $name.");
    }



    /**
     * Internal method to set atributte value incl. type-casting and modification state flag
     * @param string $name Column name
     * @param mixed $value Value
     */
    private function _setValue($name, $value)
    {
        if ($value !== NULL) {
            $type = $this->_reflection->types[$name];
            if (\class_exists($type)) {
                $value = new $type($value);
            } else {
                \settype($value, $type);
            }
        }

        if ($this->_modified[$name] == self::VALUE_NOT_SET) {
            $this->_modified[$name] = self::VALUE_SET;
        } elseif ($this->_modified[$name] == self::VALUE_SET && ($value != $this->_values[$name])) {
            $this->_modified[$name] = self::VALUE_MODIFIED;
        }

        $this->_values[$name] = $value;
    }



    /**
     * Should not by called directly
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        //load{Parent} or load{Single} or load{Children}
        if (strpos($name, 'load') === 0) {
            $VarName = substr($name, 4);
            $varName = lcfirst($VarName);
            $depth = isset($arguments[0]) && $arguments[0];

            if (($a = \array_key_exists($varName, $this->_parents)) || \array_key_exists($VarName, $this->_parents)) {
                $parentName = $a ? $varName : $VarName;
                return $this->loadParents(array($parentName), $depth);
            }

            if (($a = \array_key_exists($varName, $this->_singles)) || \array_key_exists($VarName, $this->_singles)) {
                $singleName = $a ? $varName : $VarName;
                return $this->loadSingles(array($singleName), $depth);
            }

            if (($a = \array_key_exists($varName, $this->_children)) || \array_key_exists($VarName, $this->_children)) {
                $childName = $a ? $varName : $VarName;
                return $this->loadChildren(array($childName), $depth);
            }
        }

        return static::__callStatic($name, $arguments);
    }



    /**
     * Should not by called directly
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (\method_exists(__NAMESPACE__ . '\Table\Helpers', $name)) {
            \array_unshift($arguments, new Table(\get_called_class()));
            return \call_user_func_array(array(__NAMESPACE__ . '\Table\Helpers', $name), $arguments);
        }

        $entity = \get_called_class();
        throw new Exception("Undeclared method $entity::$name().");
    }



    /**
     * Prints entity values in simple human readable form;
     * @return string
     */
    public function  __toString()
    {
        return \print_r($this->getValues(), TRUE);
    }



    /**
     * Shorthand for new Table($this)->where($args)
     */
    public function __invoke()
    {
        $args = \func_get_args();
        return \call_user_func_array(array(\get_class($this), 'getTable'), $args);
    }

    /****************** COPYING *******************/



    /**
     * Should not by called directly
     */
    public function __clone()
    {
        if (\is_array($this->_parents))
            foreach ($this->_parents as $parentName => $parentEntity) {
                if ($parentEntity !== NULL)
                    $this->_parents[$parentName] = clone $this->_parents[$parentName];
            }
        if (\is_array($this->_children))
            foreach ($this->_children as $childName => $childEntities) {
                $this->_children[$childName] = clone $this->_children[$childName];
                foreach ($this->_children[$childName] as $i => $childEntity) {
                    $this->_children[$childName][$i] = clone $this->_children[$childName][$i];
                }
            }
        if (\is_array($this->_singles))
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
                $row = dibi::fetch("SELECT * FROM [{$table->getName()}] WHERE %and", array($table->id->eq($this->_id)));
                if ($row) {
                    $this->id = $row->{$this->_reflection->primaryKeyColumn};
                    foreach ($row as $name => $value) {
                        if (\array_key_exists($name, $this->_values)) {
                            $this->_setValue($name, $value);
                            $this->_modified[$name] = self::VALUE_SET;
                        }
                    }
                    $this->_self_loaded = TRUE;
                } else {
                    return FALSE;
                }
            }

            $this->loadParents(self::EXTENDED);
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

                if ($parentName === self::EXTENDED) {
                    $parentEntity = new $parentClass($this[$this->_reflection->getColumnName($parentName)]);
                    $parentEntity->load($depth);
                } else {
                    $parentEntity = $parentClass::find($this[$this->_reflection->getColumnName($parentName)]) or
                        $parentEntity = new $parentClass;
                    if ($depth > 0) {
                        $parentEntity->load($depth);
                    }
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
        if ($singleNames === self::ALL) {
            $singleNames = \array_keys($this->_singles);
        } elseif (!\is_array($singleNames)) {
            $singleNames = (array)$singleNames;
        }

        foreach ($this->_reflection->singles as $singleName => $singleClass) {
            if (in_array($singleName, $singleNames)) {
                if ($this->_id !== NULL) {
                    $fkColumn = $singleClass::getTable()->{$this->_reflection->getForeignKeyName($singleName)};
                    $entity = $singleClass::findOne($fkColumn->eq($this->id)) or
                              $entity = new $singleClass;
                    $entity->load($depth);
                } else {
                    $entity = new $singleClass;
                }
                $this->_singles[$singleName] = $entity;
                $this->_loaded[$singleName] = $entity->isLoaded();
            }
        }
    }



    /**
     * Loads children as an instance of Table
     * @param array|string $childrenNames array of children names or self::ALL
     */
    final public function loadChildren($childrenNames = self::ALL, $depth = 0)
    {
        if ($childrenNames === self::ALL) {
            $childrenNames = \array_keys($this->_children);
        } elseif (!\is_array($childrenNames)) {
            $childrenNames = (array) $childrenNames;
        }

        foreach ($this->_reflection->children as $childName => $childClass) {
            if (in_array($childName, $childrenNames)) {
                if ($this->_id !== NULL) {
                    if (get_class($this) == $childClass) {//load children of the same class
                        $fk = 'parent_id';
                    } else {
                        $fk = $this->_reflection->getForeignKeyName($childName);
                    }
                    $fkColumn = $childClass::getTable()->$fk;
                    $children = $childClass::getTable()->where($fkColumn->eq($this->id));
                } else {
                    $children = new DataSource\ArraySource;
                }
                $this->$childName = $children;
                $this->_loaded[$childName] = TRUE;
            }
        }
    }



    /****************** DATA MANIPULATION *******************/



    /**
     * Saves the entity to database
     * @param bool $in_transaction Is method called in outer db transaction?
     * @return bool FALSE on failure, TRUE otherwise
     * @throws SavingException
     */
    final public function save($in_transaction = FALSE)
    {

        $in_transaction === TRUE or
        dibi::begin();

        try {
            //saving parents
            foreach ($this->_parents as $parentName => $parentEntity) {
                if ($parentEntity instanceof self) {
                    $parentEntity->save(TRUE);
                    $this[$this->_reflection->getColumnName($parentName)] = $parentEntity->_id;
                }
            }

            //values
            $values = $values_update = $this->_values;
            foreach ($values as $column => $value) {
                // only modified values are updated
                if ($this->_modified[$column] !== self::VALUE_MODIFIED) {
                    unset($values_update[$column]);
                }
                // DB default value will be used
                if ($value === NULL && !$this->_reflection->isNullColumn($column)) {
                    unset($values[$column]);
                    unset($values_update[$column]);
                }
                // make a reference
                if (isset($values_update[$column])) {
                    $values_update[$column] = &$values[$column];
                }
            }
            
            //entity column
            if (isset($this->_entityClass)) {
                $values[$this->_reflection->entityColumn] = $values_update[$this->_reflection->entityColumn] = $this->_entityClass;
            }

            $this->beforeSave($values);

            dibi::query(
                'INSERT INTO `' . $this->_reflection->tableName . '`', $values+array($this->_reflection->primaryKeyColumn=>$this->_id),
                'ON DUPLICATE KEY UPDATE ' . $this->_reflection->primaryKeyColumn . '=LAST_INSERT_ID(' . $this->_reflection->primaryKeyColumn . ')
                 %if', $values_update, ', %a', $values_update, '%end'
            );

            $this->afterSave($values);

            if ($this->_id === NULL) {
                $this->id = dibi::insertId();
            }

            //save singles
            foreach ($this->_singles as $singleName => $single) {
                if ($single instanceof self) {
                    $single[$this->_reflection->getForeignKeyName($singleName)] = $this->_id;
                    $single->save(TRUE);
                }
            }

            //save children
            foreach ($this->_children as $childName => $children) {
                if ($children) {
                    foreach ($children as $i => $childEntity) {
                        $childEntity[$this->_reflection->getForeignKeyName($childName)] = $this->_id;
                        $childEntity->save(TRUE);
                    }
                }
            }

        } catch (\DibiException $e) {
            $in_transaction === TRUE or
            dibi::rollback();

            throw new SavingException('Error when saving entity "' . \get_class($this) . '."', 0, $e);

            return FALSE;
        }

        $in_transaction === TRUE or
        dibi::commit();

        return TRUE;
    }



    /**
     * Called immediatelly before entity saving. Intetionally for oveloading.
     * @param array $values The values, which will be saved to database
     */
    protected function beforeSave(&$values)
    {

    }



    /**
     * Called immediatelly after entity saving. Intetionally for oveloading.
     * @param array $values The values, which were saved to database
     */
    protected function afterSave(&$values)
    {

    }



    /**
     * Deletes entity from database
     * (deleting of its chidren and singles must be ensured in database itself via foreign keys)
     * @throws Exception
     */
    final public function delete($in_transaction = FALSE)
    {
        if ($this->_id !== NULL) {
            try {
                $in_transaction === TRUE or
                dibi::begin();
                dibi::query(
                    'DELETE FROM [' . $this->_reflection->tableName . '] WHERE %and',
                    array($this->_reflection->primaryKeyColumn => $this->_id), 'LIMIT 1'
                );

                if (\array_key_exists(self::EXTENDED, $this->_parents)) {
                    $this->{self::EXTENDED}->delete(TRUE);
                }

            } catch (\DibiException $e) {
                $in_transaction === TRUE or
                dibi::rollback();
                throw new Exception("Error while deleting entity '" .  \get_class($this) . "' with id {$this->_id}", 0, $e);
            }

            $in_transaction === TRUE or
            dibi::commit();
        }
    }



    /****************** VALUES *******************/



    /**
     * Returns data in associative array form
     * @return array
     */
    public function getValues()
    {
        $values = array();
        $values[$this->_reflection->primaryKey] = $this->_id;
        foreach ($this->_properties as $prop => $column) {
            $values[$prop] = $this->_values[$column];
        }

        foreach ($this->_parents as $parentName => $parentEntity) {
            unset($values[$parentName]);
            //foreign key
            $values[$this->_reflection->getColumnName($parentName)] = $this->_values[$this->_reflection->getColumnName($parentName)];
            if ($parentName === self::EXTENDED) {
                $values = $this->{self::EXTENDED}->values + $values;
            } elseif ($parentEntity instanceof self) {
                $values[$parentName] = $parentEntity->values;
            }
        }


        foreach ($this->_singles as $singleName => $single) {
            if ($single instanceof self) {
                $values[$singleName] = $single->values;
            }
        }

        foreach ($this->_children as $childName => $children) {
            if ($children) {
                foreach ($children as $i => $childEntity) {
                    if ($childEntity instanceof self) {
                        $values[$childName][$i] = $childEntity->values;
                    }
                }
            }
        }

        return $values;
    }



    /**
     * Sets entity data from array, or any iterable object
     * @param array|object $values Values
     * @param bool $isColumns Is keys in table column format? (Default FALSE)
     */
    public function setValues($values, $isColumns = FALSE)
    {
        foreach ($values as $key=>$value) {
            if ($key === $this->_reflection->primaryKeyColumn && $isColumns) {
                $this->id = $value;
                //unset($values[$key]);
                continue;
            }

            if (\array_key_exists($key, $this->_values) && $isColumns) {
                $this->_setValue($key, $value);
                //unset($values[$key]);
                continue;
            }

            if ($key === $this->_reflection->primaryKey && !$isColumns) {
                $this->id = $value;
                //unset($values[$key]);
                continue;
            }

            if (\array_key_exists($key, $this->_properties) && !$isColumns) {
                $this->_setValue($this->_properties[$key], $value);
                //unset($values[$key]);
                continue;
            }
        }

        foreach ($values as $key=>$value) {
            if (\array_key_exists($key, $this->_parents)) {
                $this->$key->setValues($value, $isColumns);
                //unset($values[$key]);
                continue;
            }

            if (\array_key_exists($key, $this->_singles)) {
                $this->$key->setValues($value, $isColumns);
                //unset($values[$key]);
                continue;
            }

            if (\array_key_exists($key, $this->_children)) {
                foreach ($value as $i=>$childValues) {
                    $this->$key[$i]->setValues($childValues, $isColumns);
                }
               // unset($values[$key]);
                continue;
            }

        }

        //entity class name
        if (isset($values[$this->_reflection->entityColumn])) {
            $this->setEntityClass($value);
        }


        if (\array_key_exists(self::EXTENDED, $this->_parents)) {
            $this->{self::EXTENDED}->setValues($values, $isColumns);
        }
    }



    /**
     * Sets entity data from array, or any iterable object
     * @param array|object $values Values, keys corresponds to column names
     */
    final public function setColumnValues($values)
    {
        $this->setValues($values, TRUE);
    }



    /****************** STATIC METHODS *******************/



    /**
     * Registers entity
     *  - defines global function with the same name
     */
    public static function register()
    {
        $ns_function_name = \get_called_class();
        $pos = \strrpos($ns_function_name, '\\');
        if ($pos === FALSE) {
            $function_name = $ns_function_name;
            $namespace = '';
        } else {
            $function_name = substr($ns_function_name, $pos+1);
            $namespace = substr($ns_function_name, 0, $pos);
        }
        if (!\function_exists($ns_function_name)) {
            eval("
                namespace $namespace {
                    function $function_name() {
                        \$args = \\func_get_args();
                        return \\call_user_func_array(array('$ns_function_name', 'getTable'), \$args);
                    }
                }
            ");
        }
    }


    /**
     * Returns entity reflection instance
     * @return Reflection\EntityReflection
     */
    public static function getReflection()
    {
        if (!isset(self::$_REFLECTIONS[get_called_class()])) {
            self::$_REFLECTIONS[get_called_class()] = Reflection\EntityReflection::create(get_called_class());
        }
        return self::$_REFLECTIONS[get_called_class()];
    }



    /**
     * Returns new instance of Table for the given Entity
     * @return Table
     */
    public static function getTable()
    {
        $t = new Table(\get_called_class());
        if (\func_num_args () > 0) {
            $args = \func_get_args();
            return \call_user_func_array(array($t, 'where'), $args);
        }
        return $t;
    }



    /**
     * @todo is it neccessary?
     */
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
        if ($name && \array_key_exists($name, $this->_values)) {
            $this->_setValue($name, $value);
        } elseif ($name == $this->_reflection->entityColumn) {
            $this->setEntityClass($value);
        } else {
            throw new Exception("Cannot set new property '$name'.");
        }
    }



    final public function offsetGet($name)
    {
        return $this->_values[$name];
    }



    final public function offsetExists($name)
    {
        return \array_key_exists($name, $this->_values);
    }



    final public function offsetUnset($name)
    {
        if (\array_key_exists($name, $this->_values)) {
            unset($this->_values[$name]);
        }
    }



    final public function getIterator()
    {
        return new \ArrayIterator($this->_values);
    }

}
?>