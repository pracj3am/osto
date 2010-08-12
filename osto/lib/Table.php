<?php
namespace osto;



/**
 * Table query API
 * @author Jan Prachař <jan.prachar@gmail.com>
 */
class Table implements \IDataSource, \ArrayAccess
{

    /**
     * Alias delimiter
     */
    const ALIAS_DELIM = '->';

    /**
     * Entity class name
     * @var string
     */
    private $_entity;

    /**
     * Alias of table in SQL query
     * @var string
     */
    private $_alias;

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
    protected $_joined = array();

    /**
     * Is datasource $sql valid?
     * @var bool
     */
    protected $_isSqlValid = FALSE;



    /**
     * Constructor
     * @param Entity|string $entity
     * @param string $alias     Alias for SQL query
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

        $this->_alias = '$' . $this->getName();

        $this->_dataSource = new DataSource\Database;
        $this->_dataSource->setRowClass($this->_entity);

        if ($this->_reflection->isExtendingEntity()) {
            $this->_extends = new self($this->_reflection->parentEntity);
        }

        if (isset($this->_extends)) {
            $this->join($this->_extends, Entity::EXTENDED);
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
     * Returns column SQL identifier, performs autojoining!
     * @param string $name
     * @return string|bool Returns SQL identifier; FALSE if the column does not exists
     */
    protected function getColumnIdentifier($name)
    {
        if (($pos = \strpos($name, '.')) !== FALSE) {
            $relName = \substr($name, 0, $pos);
            $relations = $this->_reflection->parents + $this->_reflection->singles;

            if (isset($relations[$relName])) {
                $name = substr($name, $pos + 1);

                //auto joining
                if (isset($this->_joined[$relName])) {
                    $table = $this->_joined[$relName];
                } else {
                    $table = new self($relations[$relName]);
                    $this->join($table);
                }

                $i = $table->getColumnIdentifier($name);

                if ($i !== FALSE) {
                    return $i;
                }

            }

            if (isset($this->_extends)) {
                return $this->_extends->getColumnIdentifier($name);
            }

            return FALSE;
        }

        $i = isset($this->_reflection->columns[$name]) ? $this->_reflection->columns[$name] :
                        ( $this->_reflection->isColumn($name) ? $name : FALSE );

        if ($i !== FALSE) {
            return "$this->_alias.$i";
        }

        if (isset($this->_extends)) {
            return $this->_extends->getColumnIdentifier($name);
        }

        return FALSE;
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
            return new Table\Column($this, $this->_reflection->columns[$name]);
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
			$this->buildQuery();
            $s = $this->_dataSource->__toString();
        } catch (\Exception $e) {
            \trigger_error($e->getMessage(), \E_USER_ERROR);
            return '';
        }
        return $s;
    }



    /**
     * Sets table alias
     * @param string $alias
     */
    public function setAlias($alias)
    {
        $this->_alias = $alias;
        foreach ($this->_joined as $relName=>$table) {
            $table->setAlias($this->_alias . self::ALIAS_DELIM . $relName);
        }
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
        $c = $this->getColumnIdentifier($matches[1]);
        if ($c === FALSE) {
            throw new Exception("Undefined column '$matches[1]' for entity {$this->_entity}");
        }

        return $c;
    }



    /**
     * Returns SQL FROM clause
     * @return string SQL FROM clause
     */
    protected function getSql()
    {
        $this->buildQuery(FALSE);
        return $this->_dataSource->getSql();
    }



	/**
	 * Builds SQL FROM clause
	 */
	protected function buildQuery($emptyStack = TRUE)
	{
        static $stack;

        if ($emptyStack) {
            $stack = array();
        }

        if ($stack && \in_array($this, $stack)) {
            throw new Exception("Circular reference occured when building query.");
        }

        $stack[] = $this;

        
        if ($this->_isSqlValid) {
            return;
        }


        $sql = '[' . $this->getName() . '] AS [' . $this->_alias . ']';

        foreach ($this->_joined as $relName=>$table) {
            $sql .= ' JOIN (' . $table->getSql() . ')';

            if (isset($this->_reflection->parents[$relName])) {
                $c1 = $this->_reflection->columns[$relName];
                $c2 = $table->_reflection->primaryKeyColumn;

                if ($c1 === $c2) {
                    $sql .= " USING ([$c1])";
                } else {
                    $sql .= " ON [{$this->_alias}.$c1] = [{$table->_alias}.$c2]";
                }

            } elseif (isset($this->_reflection->singles[$relName]) || isset($this->_reflection->children[$relName])) {
                $c1 = $this->_reflection->primaryKeyColumn;
                $c2 = $this->_reflection->getForeignKeyName($relName);

                if ($c1 === $c2) {
                    $sql .= " USING([$c1])";
                } else {
                    $sql .= " ON [{$this->_alias}.$c1] = [{$table->_alias}.$c2]";
                }

            }
        }

        $this->_dataSource->setSql($sql);

	}



    /**
     * Invalidates SQL FROM clause
     */
    protected function invalidateQuery()
    {
        $this->_dataSource->release();
        $this->_isSqlValid = FALSE;
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
     * @param string $relName   name of the relation
     * @return Table            provides a fluent interface
     */
    public function join(Table $table, $relName = NULL)
    {
        if ($relName === NULL) {
            $relName = $this->_reflection->getRelationWith($table->_reflection)
                    or function(){throw new Exception("No relation exists between table '" . $this->getName() . "' and '" . $table->getName() . "'.");};
        }

        if (isset($this->_joined[$relName])) {
            throw new Exception("Tables '" . $this->getName() . "' and '" . $table->getName() . "' are already joined.");
        }

        if (\in_array($this, $table->_joined)) {
            throw new Exception("Circular reference between tables '" . $this->getName() . "' and '" . $table->getName() . "'.");
        }

        $table->setAlias($this->_alias . self::ALIAS_DELIM . $relName);
        $this->_joined[$relName] = $table;

        $this->invalidateQuery();

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
	 * Returns (and queries) DibiResult.
	 * @return DibiResult
	 */
	public function getResult()
	{
		$this->buildQuery();
		return $this->_dataSource->getResult();
	}



    /**
     * Gets iterator
     * @return Traversable
     */
    public function getIterator()
    {
		$this->buildQuery();
        return $this->_dataSource->getIterator();
    }


	
	/**
	 * Generates, executes SQL query and fetches the single row.
	 * @return Entity|FALSE  array on success, FALSE if no next record
	 */
	public function fetch()
	{
		$this->buildQuery();
		return $this->_dataSource->fetch();
	}



	/**
	 * Like fetch(), but returns only first field.
	 * @return mixed  value on success, FALSE if no next record
	 */
	public function fetchSingle()
	{
		$this->buildQuery();
		return $this->_dataSource->fetchSingle();
	}



	/**
	 * Fetches all records from table.
	 * @return array
	 */
	public function fetchAll()
	{
		$this->buildQuery();
		return $this->_dataSource->fetchAll();
	}



	/**
	 * Fetches all records from table and returns associative tree.
	 * @param  string  associative descriptor
	 * @return array
	 */
	public function fetchAssoc($assoc)
	{
		$this->buildQuery();
		return $this->_dataSource->fetchAssoc($assoc);
	}



	/**
	 * Fetches all records from table like $key => $value pairs.
	 * @param  string  associative key
	 * @param  string  value
	 * @return array
	 */
	public function fetchPairs($key = NULL, $value = NULL)
	{
		$this->buildQuery();
		return $this->_dataSource->fetchPairs($key, $value);
	}



	/**
	 * Returns the number of rows in a given data source.
	 * @return int
	 */
	public function getTotalCount()
	{
		$this->buildQuery();
		return $this->_dataSource->getTotalCount();
	}


    /**
     * Gets number of items
     * @return int
     */
    public function count()
    {
		$this->buildQuery();
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