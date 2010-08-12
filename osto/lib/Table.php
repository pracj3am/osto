<?php
namespace osto;



/**
 * Table query API
 * @author Jan Prachař <jan.prachar@gmail.com>
 */
class Table implements \IDataSource, \ArrayAccess
{

    /**#@+
     * Alias consts
     */
    const ALIAS = '$this';
    const ALIAS_DELIM = '->';
    const ALIAS_PARENT = 'parent0xrzu';
    /**#@- */

    /**
     * Entity class name
     * @var string
     */
    private $_entity;

    /**
     * Instance of DataSource
     * @var DataSource
     */
    private $_dataSource;

    /**
     * Reflection of the entity
     * @var Reflection\EntityReflection
     */
    protected $_reflection;

    /**
     * Table which corresponds to extended entity
     * @var Table
     */
    protected $_extends;

    /**
     * Array of tables that has been already joined
     * @var array
     */
    private $_joined = array();



    /**
     * Constructor
     * @param Entity|string $entity
     */
    public function __construct($entity)
    {
        if (is_string($entity) && \class_exists($entity)) {
            $this->_entity = $entity;
        } elseif ($entity instanceof Entity) {
            $this->_entity = $entity->getEntityClass();
        } else {
            throw new Exception("$entity is neither entity class name nor entity itself.");
        }

        $entity = $this->_entity;
        try {
            $this->_reflection = $entity::getReflection();
        } catch (Exception $e) {
            throw new Exception("Can't create reflection for entity '$entity'", 0, $e);
        }

        $this->_dataSource = new DataSource\Database;
        $this->_dataSource->setSql('[' . $this->getName() . '] AS [' . self::ALIAS . ']');
        $this->_dataSource->setRowClass($this->_entity);

        if ($this->_reflection->isExtendingEntity()) {
            $this->_extends = new self($this->_reflection->parentEntity);
        }

        if (isset($this->_extends)) {
            $table = $this;
            $alias = self::ALIAS;
            while (isset($table->_extends)) {
                $etn = $table->_extends->getName();
                $alias = $alias . self::ALIAS_DELIM . self::ALIAS_PARENT;
                $this->_dataSource->join("[$etn] AS [$alias] USING ([{$table->_reflection->columns[Entity::EXTENDED]}])");

                $table = $table->_extends;
            }
        }

    }



    /**
     * Returns table name
     * @return string
     */
    public function getName()
    {
        return $this->_reflection->tableName;
    }



    /**
     * Return SQL FROM clause
     * @param string $alias Optionally alias name (used instead of '$this')
     * @return string
     */
    protected function getSql($alias = NULL)
    {
        $sql = $this->_dataSource->getSql();
        if ($alias === NULL) {
            return $sql;
        }

        return str_replace(self::ALIAS, $alias, $sql);
    }


    
    /**
     * Getter for table properties
     * @param string $name
     * @return Table\Column
     */
    public function __get($name)
    {
        $entity = $this->_entity;

        if ($this->_reflection->isProperty($name)) {
            return new Table\Column($this, $this->_reflection->getColumnName($name));
        }

        if ($this->_reflection->isColumn($name)) {
            return new Table\Column($this, $name);
        }

        if ($name == 'id') {
            return new Table\Column($this, $this->_reflection->primaryKeyColumn);
        }

        if (isset($this->_extends) && isset($this->_extends->$name)) {
            return $this->_extends->$name;
        }

        throw new Exception("Undeclared column or property $name.");
    }


    public function __isset($name)
    {
        if ($this->_reflection->isProperty($name) || $this->_reflection->isColumn($name) || $name === 'id') {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * DataSource mixin
     * @param string $name Method name
     * @param array $args  Arguments
     */
    public function __call($name, $args)
    {
        return \call_user_func_array(array($this->_dataSource, $name), $args);
    }



    /**
     * Returns SQL query.
     * @return string
     */
    public function  __toString()
    {
        try {
            $s = $this->_dataSource->__toString();
        } catch (\Exception $e) {
            \trigger_error($e->getMessage(), \E_USER_ERROR);
            return '';
        }
        return $s;
    }



    /**
     * Insert table name before column name in substitution string
     * @param string|array $string
     * @return string|array
     */
    private function _translateColumns($string) {
        if (\is_array($string)) {
            foreach ($string as &$s) {
                $s = $this->_translateColumns($s);
            }
            return $string;
        }
        return \is_string($string) ? \preg_replace_callback('/:(\S*?):/', array($this, '_translateCb'), $string) : $string;
    }



    /**
     * Translate column names callback
     * @param array $matches
     * @return string
     * @throws Exception
     */
    private function _translateCb($matches)
    {
        $c = $this->_reflection->getColumnName($matches[1]);
        if ($c === FALSE) {
            throw new Exception("Undefined column '$matches[1]' for entity {$this->_entity}");
        }

        //auto-joining
        $pair = explode('.', $c);
        if (isset($pair[1]) && !isset($this->_joined[$pair[0]])) {
            if (isset($this->_reflection->parents[$pair[0]])) {
                $entity = $this->_reflection->parents[$pair[0]];

            } elseif (isset($this->_reflection->singles[$pair[0]])) {
                $entity = $this->_reflection->singles[$pair[0]];

            } elseif (isset($this->_reflection->children[$pair[0]])) {
                $entity = $this->_reflection->children[$pair[0]];

            } else {
                throw new Exception("Undefined relation '$pair[0]'");
            }
            $table = new self($entity);
            $this->join($table, $pair[0]);
        }
        return self::ALIAS . (isset($pair[1]) ? self::ALIAS_DELIM : '.') . $c;
    }


    /**
     * Selects columns to query.
     * @param  string|array|Table\Column  column name or array of column names
     * @param  string        column alias
     * @return Table         provides a fluent interface
     */
    public function select($col, $as = NULL)
    {
        if (\is_array($col)) {
            $col2 = array();
            foreach ($col as $k=>$c) {
                $col2[$this->_translateColumns($k)] = $this->_translateColumns((string)$c);
            }
            unset($col);
            $col = $col2;
        } else {
            $col = $this->_translateColumns((string)$col);
        }
        $this->_dataSource->select($col, $as);
        return $this;
    }



    /**
     * Joins table to SQL query
     * @param Table $table
     * @param string $alias   name of the relation
     * @return Table          provides a fluent interface
     */
    public function join(Table $table, $alias = NULL)
    {
        if ($alias === NULL) {
            $alias = $this->_reflection->getRelationWith($table->_reflection);
        }

        if ($alias) {
            $this->_joined[$alias] = 1;
            
            $sql = '(' . $table->getSql(self::ALIAS . self::ALIAS_DELIM . $alias) . ')';

            if (isset($this->_reflection->parents[$alias])) {
                $sql .= ' ON ['.self::ALIAS.'.'.$this->_reflection->getColumnName($alias).'] = ['.self::ALIAS.self::ALIAS_DELIM.$alias.'.'.$table->_reflection->primaryKeyColumn.']';

            } elseif (isset($this->_reflection->singles[$alias]) || isset($this->_reflection->children[$alias])) {
                $sql .= ' ON ['.self::ALIAS.'.'.$this->_reflection->primaryKeyColumn.'] = ['.self::ALIAS.self::ALIAS_DELIM.$alias.'.'.$this->_reflection->getForeignKeyName($alias).']';

            }
        } else {
            $sql = $table->getSql();
        }

        $this->_dataSource->join($sql);

        return $this;
    }



    /**
     * Adds conditions to query.
     * @param  mixed  conditions
     * @return Table  provides a fluent interface
     */
    public function where($cond)
    {
        $args = $this->_translateColumns(\func_get_args());
        \call_user_func_array(array($this->_dataSource, 'where'), $args);
        return $this;
    }



    /**
     * Selects columns to order by.
     * @param  string|array|Table\Column  column name or array of column names
     * @param  string        sorting direction
     * @return Table         provides a fluent interface
     */
    public function orderBy($col, $sorting = 'ASC')
    {
        if (\is_array($col)) {
            $col2 = array();
            foreach ($col as $k=>$c) {
                $col2[$this->_translateColumns($k)] = $this->_translateColumns((string)$c);
            }
            unset($col);
            $col = $col2;
        } else {
            $col = $this->_translateColumns((string)$col);
        }
        $this->_dataSource->orderBy($this->_translateColumns($col), $sorting);
        return $this;
    }



    /**
     * Limits number of rows.
     * @param  int limit
     * @param  int offset
     * @return Table provides a fluent interface
     */
    public function applyLimit($limit, $offset = NULL)
    {
        $this->_dataSource->applyLimit($limit, $offset);
        return $this;
    }



    /**
     * Gets iterator
     * @return Traversable
     */
    public function getIterator()
    {
        return $this->_dataSource->getIterator();
    }



    /**
     * Gets number of items
     * @return int
     */
    public function count()
    {
        return $this->_dataSource->count();
    }



    public function offsetSet($name, $value)
    {
       $this->_dataSource->offsetSet($name, $value);
    }



    public function offsetGet($name)
    {
        return $this->_dataSource->offsetGet($name);
    }



    public function offsetExists($name)
    {
        return $this->_dataSource->offsetExists($name);
    }



    public function offsetUnset($name)
    {
        $this->_dataSource->offsetUnset($name);
    }

}