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
        $this->_dataSource->setSql('[' . $this->getName() . '] AS [' . $this->getAlias() . ']');
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
     * Returns table alias
     * @return string
     */
    public function getAlias()
    {
        return '$' . $this->getName();
    }



    /**
     * Returns column SQL identifier, performs autojoining!
     * @param string $name
     * @return string|bool Returns FALSE if the column does not exists
     */
    public function getColumnIdentifier($name, $alias = FALSE)
    {
        if (($pos = \strpos($name, '.')) !== FALSE) {
            $relName = \substr($name, 0, $pos);
            $relations = $this->_reflection->parents + $this->_reflection->singles;

            if (isset($relations[$relName])) {
                $class = $relations[$relName];
                $name = substr($name, $pos + 1);

                /*if (isset($this->_joined[$relName])) {
                    $table = $this->_joined[$relName];
                } else {*/
                    $table = new self($class);
                //}

                $r = $table->getColumnIdentifier($name, $relName);
                $this->join($table);
                return $r === FALSE ? $r : ($alias ? $alias . self::ALIAS_DELIM : '') . $r;
            }

            return FALSE;
        }

        $r = ( isset($this->_reflection->columns[$name]) ? $this->_reflection->columns[$name] :
                        ( $this->_reflection->isColumn($name) ? $name : FALSE )
                );
        if ($r !== FALSE) {
            return ($alias ? $alias . '.' : '') . $r;
        }

        if (isset($this->_extends)) {
            return $this->_extends->getColumnIdentifier($name, ($alias ? $alias . Table::ALIAS_DELIM : '') . Entity::EXTENDED);
        }

        return FALSE;
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

        return str_replace($this->getAlias(), $alias, $sql);
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
        return $this->getAlias() . (isset($pair[1]) ? self::ALIAS_DELIM : '.') . $c;
    }



	/**
	 * Builds SQL
	 */
	private function buildQuery()
	{

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
            if (isset($this->_joined[$alias])) {
                return $this;
            }
            
            $this->_joined[$alias] = TRUE;

            $aliasJoined = $this->getAlias() . self::ALIAS_DELIM . $alias;
            
            $sql = '(' . $table->getSql($aliasJoined) . ')';

            if (isset($this->_reflection->parents[$alias])) {
                $c1 = $this->_reflection->columns[$alias];
                $c2 = $table->_reflection->primaryKeyColumn;
                
                if ($c1 === $c2) {
                    $sql .= " USING ([$c1])";
                } else {
                    $sql .= " ON [" . $this->getAlias() . ".$c1] = [$aliasJoined.$c2]";
                }

            } elseif (isset($this->_reflection->singles[$alias]) || isset($this->_reflection->children[$alias])) {
                $c1 = $this->_reflection->primaryKeyColumn;
                $c2 = $this->_reflection->getForeignKeyName($alias);

                if ($c1 === $c2) {
                    $sql .= " USING([$c1])";
                } else {
                    $sql .= " ON [" . $this->getAlias() . ".$c1] = [$aliasJoined.$c2]";
                }

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