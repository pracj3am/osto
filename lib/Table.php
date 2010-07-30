<?php
namespace osto;



/**
 * Table query API
 * @author Jan Prachař <jan.prachar@gmail.com>
 */
class Table implements \IDataSource
{

    const ALIAS = '$this';
	const ALIAS_DELIM = '->';

    
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

        if ($this->_reflection->isExtendingEntity()) {
            $this->_extends = new self($this->_reflection->parentEntity);
        }

        if (isset($this->_extends)) {
            $table = $this;
            $sql = "SELECT * FROM [{$this->_reflection->tableName}] ";
            while (isset($table->_extends)) {
                $etn = $table->_extends->getName();
                $sql .= "JOIN [$etn] USING ([{$table->_reflection->columns[Entity::EXTENDED]}]) ";

                $table = $table->_extends;
            }
        } else {
            $sql = $this->_reflection->tableName;
        }

        $this->_dataSource = new DataSource\Database($sql);
        $this->_dataSource->setRowClass($this->_entity);
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

        if ($name === 'id') {
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
        return $this->_dataSource->__toString();
    }



    /**
     * Selects columns to query.
     * @param  string|array  column name or array of column names
     * @param  string        column alias
     * @return Table         provides a fluent interface
     */
    public function select($col, $as = NULL)
    {
        $this->_dataSource->select($col, $as);
        return $this;
    }



    /**
     * Adds conditions to query.
     * @param  mixed  conditions
     * @return Table  provides a fluent interface
     */
    public function where($cond)
    {
        \call_user_func_array(array($this->_dataSource, 'where'), \func_get_args());
        return $this;
    }



    /**
     * Selects columns to order by.
     * @param  string|array  column name or array of column names
     * @param  string        sorting direction
     * @return Table         provides a fluent interface
     */
    public function orderBy($row, $sorting = 'ASC')
    {
        $this->_dataSource->orderBy($row, $sorting);
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

}