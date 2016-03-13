<?php

namespace osto;

use dibi;
use osto\Table;



abstract class Entity implements \ArrayAccess, \IteratorAggregate, \Serializable
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
    protected static $reflections = array();
    protected static $registered = array();

    /** @var integer */
    protected static $transaction_nesting_level = 0;

    private $_id;
    private $_values = array();
    private $_modified = array();
    private $_self_modified;
    private $_parents = array();
    private $_children = array();
    private $_singles = array();
    private $_loaded;
    private $_entityClass;
    private $_snapshot;

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
        //Automatic registering the entity class
        $this::register();

        $this->initialize();
        if (\is_array($id)) {
			$this->_loaded = TRUE;
            $this->setColumnValues($id);
        } else {
            $this->id = $id;
        }
    }



    /**
     * Factory for standalone entity
     *  - it looks for standalone entity in descendant entity classes until it finds one
     * @param mixed $id Primary key
     * @return Entity
     */
    public static function create($id)
    {
        $class = \get_called_class();
        $entity = new $class($id);
        $entity->load();
        if ($entity->isStandalone()) {
            return $entity;
        }

        $r = static::getReflection();
        $entityColumn = $r->getEntityColumn();

        $values = $entity->_values;
        $values[$r->getPrimaryKeyColumn()] = $entity->_id;
        $values[$r->getEntityColumn()] = $entity->getEntityClass();

        return static::createFromValues($values);

    }



    /**
     * Factory for standalone entity
     *  - it looks for standalone entity in descendant entity classes until it finds one
     * @param array $values
     * @return Entity
     */
    public static function createFromValues(array $values)
    {
        $class = \get_called_class();
        $r = static::getReflection();
        $entityColumn = $r->getEntityColumn();
        if (!isset($values[$entityColumn]) || $class === $values[$entityColumn]) {
            return new $class($values);
        }

        $sClass = $values[$entityColumn];
        $sTable = $sClass::getTable();
        return $sTable->where($sTable->id->eq($values[$r->getPrimaryKeyColumn()]))->fetch();
    }



    /**
     * Initializes entity internal properties. Called by the constructor.
     * @return void
     */
    protected function initialize()
    {
        $this->_loaded = FALSE;
        $this->_self_modified = self::VALUE_NOT_SET;

        $this->_reflection = $this::getReflection();

        foreach ($this->_reflection->columns as $prop=>$column) {
            if ($prop != $this->_reflection->primaryKey && $prop != self::EXTENDED) {
                $this->_modified[$column] = self::VALUE_NOT_SET;
                $this->_values[$column] = NULL;
            }
        }

        $this->initializeRelations();
    }



    /**
     * Initialize entity relations. Called by constructor and after unserialization.
     * @return void
     */
    protected function initializeRelations()
    {
        if ($this->_reflection->isExtendingEntity()) {
            $this->__get(self::EXTENDED)->setEntityClass(\get_class($this));
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
            return $this->_id;
        }

        /***** parents *****/

        if ($_name && isset($this->_reflection->parents[$_name])) {
            return @$this->_parents[$_name];
        }

        // lazy loading
        if (isset($this->_reflection->parents[$name])) {
            if (!isset($this->_parents[$name])) {
                try {
                    $this->loadParents($name);
                } catch (\Exception $e) {
                    $this->_parents[$name] = new $this->_reflection->parents[$name];
                    $m = $e->getMessage();
                    \trigger_error("Loading of $name failed ($m).", E_USER_NOTICE);
                }
            }
            return $this->_parents[$name];
        }

        /***** singles *****/

        if ($_name && isset($this->_reflection->singles[$_name])) {
            return @$this->_singles[$_name];
        }

        //lazy loading
        if (isset($this->_reflection->singles[$name])) {
            if (!isset($this->_singles[$name])) {
                try {
                    $this->loadSingles($name);
                } catch (\Exception $e) {
                    $this->_singles[$name] = new $this->_reflection->singles[$name];
                    $m = $e->getMessage();
                    \trigger_error("Loading of $name failed ($m).", E_USER_NOTICE);
                }
                $this->loadSingles($name);
            }
            return $this->_singles[$name];
        }

        /***** children *****/

        if ($_name && isset($this->_reflection->children[$_name])) {
            return @$this->_children[$_name];
        }

        // lazy loading
        if (isset($this->_reflection->children[$name])) {
            if (!isset($this->_children[$name])) {
                try {
                    $this->loadChildren($name);
                } catch (\Exception $e) {
                    $this->_children[$name] = new DataSource\ArraySource;
                    $m = $e->getMessage();
                    \trigger_error("Loading of $name failed ($m).", E_USER_NOTICE);
                }
            }
            return $this->_children[$name];
        }

        /***** values *****/

        if ($_name && isset($this->_reflection->columns[$_name]) && !isset($this->_reflection->parents[$_name])) {
            return $this->_values[$this->_reflection->columns[$_name]];
        }

        if (\method_exists($this, ($m_name = Helpers::getter($name)))) {//get{Name}
            return $this->{$m_name}();
        }

        if (isset($this->_reflection->columns[$name]) && !isset($this->_reflection->parents[$name])) {
            return $this->_values[$this->_reflection->columns[$name]];
        }

        /***** inheritance *****/

        if ($this->_reflection->isExtendingEntity()) {
            return $this->_parents[self::EXTENDED]->$name;
        }

        $class = \get_called_class();
        throw new Exception("Undeclared property {$class}->{$name}.");
    }



    /**
     * Should not by called directly
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (
                $name == 'id' || $name == $this->_reflection->primaryKey ||
                \method_exists($this, Helpers::getter($name)) ||
                isset($this->_reflection->columns[$name]) && !isset($this->_reflection->parents[$name]) ||
                isset($this->_children[$name]) ||
                isset($this->_parents[$name]) ||
                isset($this->_singles[$name]) ||
                $this->_reflection->isExtendingEntity() && isset($this->_parents[self::EXTENDED]->$name)
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
        if ($name == 'id' || $name == $this->_reflection->primaryKey || $name == $this->_reflection->getPrimaryKeyColumn()) {
            $this->_setId($value);
            return;
        }

        //parents
        if (isset($this->_reflection->parents[$name])) {
            if ($value === NULL || \is_object($value) && $value instanceof $this->_reflection->parents[$name]) {
                $this->_parents[$name] = $value;
                return;
            }
            throw new Exception("Property $name must be entity of class ".$this->_reflection->parents[$name]);
        }

        //singles
        if (isset($this->_reflection->singles[$name])) {
            if ($value === NULL || \is_object($value) && $value instanceof $this->_reflection->singles[$name]) {
                $this->_singles[$name] = $value;
                return;
            }
            throw new Exception("Property $name must be entity of class ".$this->_reflection->singles[$name]);
        }

        //children
        if (isset($this->_reflection->children[$name])) {
            if ($value === NULL || \is_object($value) && $value instanceof \IDataSource) {
                $this->_children[$name] = $value;
                return;
            }
            throw new Exception("Property $name must implement interface IDataSource.");
        }

        /***** values *****/

        if ($_name && isset($this->_reflection->columns[$_name]) && !isset($this->_reflection->parents[$_name]) ) {
            return $this->_setValue($this->_reflection->columns[$_name], $value);
        }

        if (\method_exists($this, ($m_name = Helpers::setter($name)))) {//set{Name}
            return $this->{$m_name}($value);
        }

        if (isset($this->_reflection->columns[$name]) && !isset($this->_reflection->parents[$name]) ) {
            return $this->_setValue($this->_reflection->columns[$name], $value);
        }

        //inheritance
        if ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED]->$name = $value;
            return;
        }

        $class = \get_called_class();
        throw new Exception("Undeclared property {$class}->{$name}.");
    }



    /**
     * Internal method to set primary key value
     * @param integer $value
     */
    private function _setId($value)
    {
        $value === NULL or \settype($value, $this->_reflection->types[$this->_reflection->getPrimaryKeyColumn()]);
        $newId = $value === 0 ? NULL : $value;
        if ($this->_id !== $newId && $this->_id !== NULL) {
            $this->_self_modified = self::VALUE_MODIFIED;
            $this->_modified = \array_fill_keys(\array_keys($this->_values), self::VALUE_MODIFIED);
            if ($newId !== NULL) {
                \trigger_error("OSTO: Id of entity '".\get_class($this)."' has changed.", E_USER_WARNING);
            }
        }
        $this->_id = $newId;

        //inheritance
        if ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED]->id = $value;
        }
    }



    /**
     * Internal method to set atributte value incl. type-casting and modification state flag
     * @param string $name Column name
     * @param mixed $value Value
     */
    private function _setValue($name, $value)
    {
        if (!isset($this->_modified[$name])) {
            return;
        }

        if ($value !== NULL) {
            $type = $this->_reflection->types[$name];
            if ($type == 'string' || $type == 'int' || $type == 'integer' || $type == 'bool' || $type == 'float') {
                \settype($value, $type);
            } elseif (\class_exists($type)) {
                if (!$value instanceof $type) {
                    $value = new $type($value);
                }
            } else {
                \settype($value, $type);
            }
        }

        if ($this->_id === NULL) { //seting values of entity without id
            $this->_self_modified = self::VALUE_MODIFIED;
            $this->_modified[$name] = self::VALUE_MODIFIED;
        } elseif ($this->_modified[$name] == self::VALUE_NOT_SET) {
            $this->_self_modified = self::VALUE_SET;
            $this->_modified[$name] = self::VALUE_SET;
        } elseif ($this->_modified[$name] == self::VALUE_SET && ($value !== $this->_values[$name])) {
            $this->_self_modified = self::VALUE_MODIFIED;
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

            if (($a = isset($this->_reflection->parents[$varName])) || isset($this->_reflection->parents[$VarName])) {
                $parentName = $a ? $varName : $VarName;
                return $this->loadParents(array($parentName), $depth);
            }

            if (($a = isset($this->_reflection->singles[$varName])) || isset($this->_reflection->singles[$VarName])) {
                $singleName = $a ? $varName : $VarName;
                return $this->loadSingles(array($singleName), $depth);
            }

            if (($a = isset($this->_reflection->children[$varName])) || isset($this->_reflection->children[$VarName])) {
                $childName = $a ? $varName : $VarName;
                return $this->loadChildren(array($childName), $depth);
            }

        //has{Parent} or has{Single} or has{Children}
        } elseif (strpos($name, 'has') === 0) {
            $VarName = substr($name, 3);
            $varName = lcfirst($VarName);

            if (($a = isset($this->_reflection->parents[$varName])) || isset($this->_reflection->parents[$VarName])) {
                $parentName = $a ? $varName : $VarName;
                return $this[$this->_reflection->columns[$parentName]] !== NULL;
            }

            if (isset($this->_reflection->singles[$childName=$varName]) || isset($this->_reflection->singles[$childName=$VarName]) ||
                   isset($this->_reflection->children[$childName=$varName]) || isset($this->_reflection->children[$childName=$VarName])) {

                $fk = $this->_reflection->getForeignKeyName($childName);
                $childClass = @$this->_reflection->children[$childName] or
                        $childClass = $this->_reflection->singles[$childName];
                $fkColumn = $childClass::getTable()->$fk;
                $tmpResult = $childClass::getTable()->where($fkColumn->eq($this->id));
                if ($arguments) {
                    $tmpResult = \call_user_func_array(array($tmpResult, 'where'), $arguments);
                }
                return $tmpResult->count() > 0;
            }
        }


        /***** inheritance *****/

        if ($this->_reflection->isExtendingEntity()) {
            return \call_user_func_array(array($this->_parents[self::EXTENDED], $name) , $arguments);
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
            $class = \get_called_class();
            \array_unshift($arguments, $class::getTable());
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
        if (\is_array($this->_parents)) {
            foreach ($this->_parents as $parentName => $parentEntity) {
                if ($parentEntity instanceof self) {
                    $this->_parents[$parentName] = clone $this->_parents[$parentName];
                }
            }
        }
        if (\is_array($this->_children)) {
            foreach ($this->_children as $childName => $childEntities) {
                if ($childEntities instanceof \IDataSource) {
                    $children = new DataSource\ArraySource;
                    foreach ($this->_children[$childName] as $childEntity) {
                        $children[] = clone $childEntity;
                    }
                    $this->_children[$childName] = $children;
                }
            }
        }
        if (\is_array($this->_singles)) {
            foreach ($this->_singles as $singleName => $singleEntity) {
                if ($singleEntity instanceof self) {
                    $this->_singles[$singleName] = clone $this->_singles[$singleName];
                }
            }
        }
    }



    /**
     * Copies entity to new instance
     * @return Entity new instance
     */
    public function copy()
    {
        $copy = clone $this;
        $copy->id = NULL;
        foreach ($copy->_modified as $name => $v) {
            $copy->_modified[$name] = self::VALUE_MODIFIED;
        }
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
                $copy->_children[$childName] = new DataSource\ArraySource;
                foreach ($this->_children[$childName] as $childEntity) {
                    $copy->_children[$childName][] = $childEntity->copy();
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
        return $this->_loaded;
    }



    /**
     * Loads entity data
     * @param int $depth depth of loading recursion
     * @return bool is loading successfull
     */
    final public function load($depth = 0)
    {
        if ($this->_id !== NULL) {
            if (!($this->_loaded)) {
                $table = static::getTable();
                $row = dibi::fetch("SELECT * FROM [{$table->getName()}] WHERE %and", array($table->id->eq($this->_id)));
                if ($row) {
                    $this->id = $row->{$this->_reflection->getPrimaryKeyColumn()};
                    unset($row->{$this->_reflection->getPrimaryKeyColumn()});
                    foreach ($row as $name => $value) {
                        if (isset($this[$name])) {
                            $this[$name] = $value;
                            $this->_modified[$name] = self::VALUE_SET;
                        }
                    }
                    $this->_loaded = TRUE;
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
            $parentNames = \array_keys($this->_reflection->parents);
        } elseif (!\is_array($parentNames)) {
            $parentNames = (array)$parentNames;
        }

        foreach ($this->_reflection->parents as $parentName => $parentClass) {
            if (in_array($parentName, $parentNames)) {

                if ($parentName === self::EXTENDED) {
                    $parentEntity = new $parentClass($this->_id);
                    $parentEntity->load($depth);
                } else {
                    $table = $parentClass::getTable();
                    $parentEntity = Table\Helpers::find($table, $this[$this->_reflection->columns[$parentName]]) or
                        $parentEntity = new $parentClass;
                    if ($depth > 0) {
                        $parentEntity->load($depth);
                    }
                    unset($table);
                }

                $this->_parents[$parentName] = $parentEntity;
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
            $singleNames = \array_keys($this->_reflection->singles);
        } elseif (!\is_array($singleNames)) {
            $singleNames = (array)$singleNames;
        }

        foreach ($this->_reflection->singles as $singleName => $singleClass) {
            if (in_array($singleName, $singleNames)) {
                if ($this->_id !== NULL) {
                    $table = $singleClass::getTable();
                    $fkColumn = $table->{$this->_reflection->getForeignKeyName($singleName)};
                    $entity = Table\Helpers::findOne($table, $fkColumn->eq($this->id)) or
                              $entity = new $singleClass;
                    $entity->load($depth);
                    unset($table);
                } else {
                    $entity = new $singleClass;
                }
                $this->_singles[$singleName] = $entity;
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
            $childrenNames = \array_keys($this->_reflection->children);
        } elseif (!\is_array($childrenNames)) {
            $childrenNames = (array) $childrenNames;
        }

        foreach ($this->_reflection->children as $childName => $childClass) {
            if (in_array($childName, $childrenNames)) {
                if ($this->_id !== NULL) {
                    $fk = $this->_reflection->getForeignKeyName($childName);
                    $fkColumn = $childClass::getTable()->$fk;
					// turn on column name translating
					$whereClause = preg_replace('/\[([^\[\]]*)\]/', '[:\1:]', $fkColumn->eq($this->id));
                    $children = $childClass::getTable()->where($whereClause);
                } else {
                    $children = new DataSource\ArraySource;
                }
                $this->$childName = $children;
            }
        }
    }



    /****************** DATA MANIPULATION *******************/


    /**
     * Starts the transaction
     */
    public static function begin()
    {
        if (self::$transaction_nesting_level == 0) {
            dibi::begin();
        }
        ++self::$transaction_nesting_level;
    }



    /**
     * Commits the transaction
     * @var integer $levels Number of levels to commit
     */
    public static function commit($levels = NULL)
    {
        if ($levels === NULL || $levels >= self::$transaction_nesting_level) {
            dibi::commit();
            self::$transaction_nesting_level = 0;
        }
        self::$transaction_nesting_level -= $levels;
    }



    /**
     * Rollbacks the transaction
     */
    public static function rollback()
    {
        dibi::rollback();
        self::$transaction_nesting_level = 0;
        
        if (isset($this)) {
            $this->restore(TRUE);
        }
    }



    /**
     * Saves the entity to database
     * @return bool FALSE on failure, TRUE otherwise
     * @throws DatabaseException
     */
    final public function save()
    {

        self::begin();

        try {

            $this->startSave();

            //saving parents
            foreach ($this->_parents as $parentName => $parentEntity) {
                if ($parentName === self::EXTENDED) {
                    continue;
                }

                if ($parentEntity) {
                    $parentEntity->save(TRUE);
                    $this[$this->_reflection->columns[$parentName]] = $parentEntity->_id;
                }
            }
            if ($this->_reflection->isExtendingEntity()) {
                $this->_parents[self::EXTENDED]->save(TRUE);
                $this[$this->_reflection->columns[self::EXTENDED]] = $this->_parents[self::EXTENDED]->_id;
            }

            // saving only if the entity values were modified
            if ($this->_self_modified != self::VALUE_SET) {

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
                    $values[$this->_reflection->getEntityColumn()] = $values_update[$this->_reflection->getEntityColumn()] = $this->_entityClass;
                }

                //primary key
                $values = $values + array($this->_reflection->getPrimaryKeyColumn()=>$this->_id);

                $this->beforeSave($values, $values_update);

                dibi::query(
                    'INSERT INTO `' . $this->_reflection->tableName . '`', $values,
                    'ON DUPLICATE KEY UPDATE ' . $this->_reflection->getPrimaryKeyColumn() . '=LAST_INSERT_ID(' . $this->_reflection->getPrimaryKeyColumn() . ')
                     %if', $values_update, ', %a', $values_update, '%end'
                );

                if ($this->_id === NULL) {
                    $id = $values[$this->_reflection->getPrimaryKeyColumn()] = dibi::insertId();
                }

                //set modified flag
                $this->saveState();

                $this->_self_modified = self::VALUE_SET;
                $this->_modified = \array_fill_keys(\array_keys($this->_values), self::VALUE_SET);

                $this->afterSave($values, $values_update);

                if ($this->_id === NULL) {
                    $this->id = $id;
                }
            }


            //save singles
            foreach ($this->_singles as $singleName => $single) {
                if ($single) {
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
            self::rollback();

            throw new DatabaseException('Error when saving entity "' . \get_class($this) . '": ' . $e->getMessage(), 0, $e->getSql());

            return FALSE;
        }

        self::commit(1);

        return TRUE;
    }



    /**
     * Saves the current state of entity
     */
    public function saveState()
    {
        $this->_snapshot = new \stdClass;
        $this->_snapshot->_id = $this->_id;
        $this->_snapshot->_values = $this->_values;
        $this->_snapshot->_self_modified = $this->_self_modified;
        $this->_snapshot->_modified = $this->_modified;
    }



    /**
     * Restores last saved state
     */
    public function restore($recursive = TRUE)
    {
        if ($this->_snapshot) {
            $this->_id = $this->_snapshot->_id;
            $this->_values = $this->_snapshot->_values;
            $this->_self_modified = $this->_snapshot->_self_modified;
            $this->_modified = $this->_snapshot->_modified;
        }

        $this->_snapshot = NULL;
        
        if ($recursive) {
            foreach ($this->_parents as $parentName => $parentEntity) {
                $parentEntity->restore();
            }
        }
    }



    /**
     * Called when saving procedure starts. Intetionally for oveloading.
     */
    protected function startSave()
    {

    }



    /**
     * Called immediatelly before entity saving. Intetionally for oveloading.
     * @param array $values         The values, which will be inserted to database
     * @param array $values_update  The values, which will be updated in database
     */
    protected function beforeSave(&$values, &$values_update)
    {

    }



    /**
     * Called immediatelly after entity saving. Intetionally for oveloading.
     * @param array $values         The values, which were saved to database (insert)
     * @param array $values_update  The values, which were saved to database (update)
     */
    protected function afterSave(&$values, &$values_update)
    {

    }



    /**
     * Deletes entity from database
     * (deleting of its chidren and singles must be ensured in database itself via foreign keys)
     * @throws DatabaseException
     */
    final public function delete()
    {
        if ($this->_id !== NULL) {
            try {
                self::begin();

                dibi::query(
                    'DELETE FROM [' . $this->_reflection->tableName . '] WHERE %and',
                    array($this->_reflection->getPrimaryKeyColumn() => $this->_id), 'LIMIT 1'
                );

                if ($this->_reflection->isExtendingEntity()) {
                    $this->_parents[self::EXTENDED]->delete(TRUE);
                }

            } catch (\DibiException $e) {
                self::rollback();
                throw new DatabaseException("Error while deleting entity '" .  \get_class($this) . "' with id {$this->_id}", 0, $e);
            }

            self::commit(1);
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
        foreach ($this->_reflection->columns as $prop => $column) {
            if (isset($this->_modified[$column]) && !isset($this->_reflection->parents[$prop])) {
                $values[$prop] = $this->_values[$column];
            }
        }

        foreach ($this->_reflection->parents as $parentName => $parentClass) {
            unset($values[$parentName]);
            if ($parentName === self::EXTENDED) {
                $values = $this->_parents[self::EXTENDED]->getValues() + $values;
            } else {
                //foreign key
                $values[$this->_reflection->columns[$parentName]] = $this->_values[$this->_reflection->columns[$parentName]];

                if (isset($this->_parents[$parentName])) {
                    $values[$parentName] = $this->_parents[$parentName]->getValues();
                }
            }
        }


        foreach ($this->_reflection->singles as $singleName => $singleClass) {
            if (isset($this->_singles[$singleName])) {
                $values[$singleName] = $this->_singles[$singleName]->getValues();
            }
        }

        foreach ($this->_reflection->children as $childName => $childrenClass) {
            if (isset($this->_children[$childName])) {
                foreach ($this->_children[$childName] as $i => $childEntity) {
                    if ($childEntity instanceof self) {
                        $values[$childName][$i] = $childEntity->getValues();
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
            if ($key === $this->_reflection->getPrimaryKeyColumn() && $isColumns) {
                $this->id = $value;
                //unset($values[$key]);
                continue;
            }

            if (isset($this->_modified[$key]) && $isColumns) {
                $this->_setValue($key, $value);
                //unset($values[$key]);
                continue;
            }

            if ($key === $this->_reflection->primaryKey && !$isColumns) {
                $this->id = $value;
                //unset($values[$key]);
                continue;
            }

            if (isset($this->_reflection->columns[$key]) && !isset($this->_reflection->parents[$key]) && !$isColumns) {
                $this->_setValue($this->_reflection->columns[$key], $value);
                //unset($values[$key]);
                continue;
            }
        }

        foreach ($values as $key=>$value) {
            if (isset($this->_reflection->parents[$key])) {
                $this->$key->setValues($value, $isColumns);
                //unset($values[$key]);
                continue;
            }

            if (isset($this->_reflection->singles[$key])) {
                $this->$key->setValues($value, $isColumns);
                //unset($values[$key]);
                continue;
            }

            if (isset($this->_reflection->children[$key])) {
                foreach ($value as $i=>$childValues) {
                    $this->$key[$i]->setValues($childValues, $isColumns);
                }
               // unset($values[$key]);
                continue;
            }

        }


        if ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED]->setValues($values, $isColumns);
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
        $nsClassName = \get_called_class();

        if (isset(self::$registered[$nsClassName]) && self::$registered[$nsClassName]) {
            return;
        }

        $pos = \strrpos($nsClassName, '\\');
        if ($pos === FALSE) {
            $className = $nsClassName;
            $namespace = '';
        } else {
            $className = \substr($nsClassName, $pos+1);
            $namespace = \substr($nsClassName, 0, $pos);
        }


        if (!\function_exists($nsClassName)) {
            eval("
                namespace $namespace {
                    function $className() {
                        \$args = \\func_get_args();
                        return \\call_user_func_array(array('$nsClassName', 'getTable'), \$args);
                    }
                }
            ");
        }

        self::$registered[$nsClassName] = TRUE;
    }


    /**
     * Returns entity reflection instance
     * @return Reflection\EntityReflection
     */
    public static function getReflection()
    {
        if (!isset(self::$reflections[\get_called_class()])) {
            self::$reflections[\get_called_class()] = Reflection\EntityReflection::create(\get_called_class());
        }
        return self::$reflections[\get_called_class()];
    }



    /**
     * Returns new instance of Table for the given Entity
     * @return Table
     */
    public static function getTable($where = NULL)
    {
        if (\is_int($where)) {
            return self::find($where);
        }
        $t = new Table(\get_called_class());
        if ($where) {
            $args = \func_get_args();
            return \call_user_func_array(array($t, 'where'), $args);
        }
        return $t;
    }



    /****************** IMPLEMENTATION OF INTERFACES *******************/




    final public function offsetSet($name, $value)
    {
        if (!\is_int($name) && !\is_string($name)) {
            throw new Exception("Key value must be a string or an integer, not '".  \gettype($name) . "'.");
        }

        if ($name && isset($this->_modified[$name])) {
            $this->_setValue($name, $value);
        } elseif ($name == $this->_reflection->getPrimaryKeyColumn()) {
            $this->_setId($value);
        } elseif ($name == $this->_reflection->getEntityColumn()) {
            $this->setEntityClass($value);
        } elseif ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED]->offsetSet($name, $value);
        } else {
            $class = \get_called_class();
            throw new Exception("Cannot set undeclared property '{$class}[{$name}]'.");
        }
    }



    final public function offsetGet($name)
    {
        if (!\is_int($name) && !\is_string($name)) {
            throw new Exception("Key value must be a string or an integer, not '".  \gettype($name) . "'.");
        }

        if ($name && isset($this->_modified[$name])) {
            return $this->_values[$name];
        }
        if ($name == $this->_reflection->getPrimaryKeyColumn()) {
            return $this->_id;
        }
        if ($name == $this->_reflection->getEntityColumn()) {
            return $this->getEntityClass();
        }
        if ($this->_reflection->isExtendingEntity()) {
            return $this->_parents[self::EXTENDED]->offsetGet($name);
        }

        $class = \get_called_class();
        throw new Exception("Undeclared property '{$class}[{$name}]'.");
    }



    final public function offsetExists($name)
    {
        if (!\is_int($name) && !\is_string($name)) {
            throw new Exception("Key value must be a string or an integer, not '".  \gettype($name) . "'.");
        }
        return isset($this->_modified[$name]) ||
                $name == $this->_reflection->getPrimaryKeyColumn() ||
                $name == $this->_reflection->getEntityColumn() ||
                $this->_reflection->isExtendingEntity() && $this->_parents[self::EXTENDED]->offsetExists($name);
    }



    final public function offsetUnset($name)
    {
        if (!\is_int($name) && !\is_string($name)) {
            throw new Exception("Key value must be a string or an integer, not '".  \gettype($name) . "'.");
        }
        if (isset($this->_modified[$name])) {
            unset($this->_values[$name]);
        }
        if ($this->_reflection->isExtendingEntity()) {
            $this->_parents[self::EXTENDED]->offsetUnset($name);
        }
    }



    final public function getIterator()
    {
        return new \ArrayIterator($this->_values);
    }



    public function serialize()
    {
        foreach ($this->_parents as $parentName=>$parentEntity) {
            if ($parentEntity) {
                $this->_values[$this->_reflection->columns[$parentName]] = $parentEntity->_id;
            }
        }

        $vars = \get_object_vars($this);
        $vars['_reflection'] = NULL;
        $vars['_parents'] = array();
        $vars['_children'] = array();
        $vars['_singles'] = array();
        return \serialize($vars);
    }



    public function unserialize($serialized)
    {
        foreach (\unserialize($serialized) as $p=>$v) {
            $this->$p = $v;
        }
        $this->_reflection = $this::getReflection();
        $this->initializeRelations();
    }



    public function __destruct()
    {

    }

}
