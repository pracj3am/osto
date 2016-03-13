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
    private $entity;

    /**
     * Alias of table in SQL query
     * @var string
     */
    private $alias;

    /**
     * Instance of DataSource
     * @var DataSource
     */
    private $dataSource;

    /**
     * Reflection of the entity
     * @var Reflection\EntityReflection
     */
    protected $reflection;

    /**
     * Table which corresponds to extended entity
     * @var Table
     */
    protected $extends;

    /**
     * Array of tables that has been already joined
     * @var array
     */
    protected $joined = array();

    /**
     * Is datasource $sql valid?
     * @var bool
     */
    protected $isSqlValid = FALSE;



    /**
     * Constructor
     * @param Entity|string $entity
     */
    public function __construct($entity)
    {
        if (is_string($entity) && \class_exists($entity)) {
            $this->entity = $entity;
        } elseif ($entity instanceof Entity) {
            $this->entity = $entity->getEntityClass();
        } else {
            throw new Exception("'$entity' is neither entity class name nor entity itself.");
        }

        $entity = $this->entity;
        try {
            $this->reflection = $entity::getReflection();
        } catch (Exception $e) {
            throw new Exception("Can't create reflection for entity '$entity'", 0, $e);
        }

        $this->alias = '$' . $this->getName();

        $this->dataSource = new DataSource\Database;
        $this->dataSource->setRowClass($this->entity);

        if ($this->reflection->isExtendingEntity()) {
            $this->extends = new self($this->reflection->getParentEntity());
        }

        if (isset($this->extends)) {
            $this->join($this->extends, Entity::EXTENDED);
        }

    }



    /**
     * Returns table name
     * @return string
     */
    public function getName()
    {
        return $this->reflection->tableName;
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
            $relations = $this->reflection->parents + $this->reflection->singles + $this->reflection->children;

            if (isset($relations[$relName])) {
                $name = substr($name, $pos + 1);

                //auto joining
                if (isset($this->joined[$relName])) {
                    $table = $this->joined[$relName];
                } else {
                    $table = new self($relations[$relName]);
                    $this->join($table, $relName);
                }

                $i = $table->getColumnIdentifier($name);

                if ($i !== FALSE) {
                    return $i;
                }

            }

            if (isset($this->extends)) {
                return $this->extends->getColumnIdentifier($name);
            }

            return FALSE;
        }

        $i = isset($this->reflection->columns[$name]) ? $this->reflection->columns[$name] :
                        ( $this->reflection->isColumn($name) ? $name : FALSE );

        if ($i !== FALSE) {
            return \implode('.', array($this->alias, $i));
        }

        if (isset($this->extends)) {
            return $this->extends->getColumnIdentifier($name);
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
        $entity = $this->entity;

        if ($this->reflection->isProperty($name)) {
            return new Table\Column($this, $this->reflection->columns[$name]);
        }

        if ($this->reflection->isColumn($name)) {
            return new Table\Column($this, $name);
        }

        if ($name == 'id') {
            return new Table\Column($this, $this->reflection->getPrimaryKeyColumn());
        }

        if (isset($this->extends) && isset($this->extends->$name)) {
            return $this->extends->$name;
        }

        throw new Exception("Undeclared column or property $name.");
    }


    public function __isset($name)
    {
        if ($this->reflection->isProperty($name) || $this->reflection->isColumn($name) || $name === 'id') {
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
        return \call_user_func_array(array($this->dataSource, $name), $args);
    }



    /**
     * Returns SQL query.
     * @return string
     */
    public function  __toString()
    {
        try {
            $this->buildQuery();
            $s = $this->dataSource->__toString();
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
        $this->alias = $alias;
        foreach ($this->joined as $relName=>$table) {
            $table->setAlias(\implode(self::ALIAS_DELIM, array($this->alias, $relName)));
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
            throw new Exception("Undefined column '$matches[1]' for entity {$this->entity}");
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
        return $this->dataSource->getSql();
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

        
        if ($this->isSqlValid) {
            return;
        }


        $sql = array('[', $this->getName(), '] AS [', $this->alias, ']');

        foreach ($this->joined as $relName=>$table) {
            $sql[] = ' JOIN (';
            $sql[] = $table->getSql();
            $sql[] = ')';

            if (isset($this->reflection->parents[$relName])) {
                $c1 = $this->reflection->columns[$relName];
                $c2 = $table->reflection->getPrimaryKeyColumn();

                if ($c1 === $c2) {
                    $sql[] = ' USING (['; $sql[] = $c1;  $sql[] = '])';
                } else {
                    $sql[] = ' ON [';  $sql[] = $this->alias;  $sql[] = '.';  $sql[] = $c1; $sql[] = '] = [';
                    $sql[] = $table->alias; $sql[] = '.'; $sql[] = $c2; $sql[] = ']';
                }

            } elseif (isset($this->reflection->singles[$relName]) || isset($this->reflection->children[$relName])) {
                $c1 = $this->reflection->getPrimaryKeyColumn();
                $c2 = $this->reflection->getForeignKeyName($relName);

                if ($c1 === $c2) {
                    $sql[] = ' USING (['; $sql[] = $c1;  $sql[] = '])';
                } else {
                    $sql[] = ' ON [';  $sql[] = $this->alias;  $sql[] = '.';  $sql[] = $c1; $sql[] = '] = [';
                    $sql[] = $table->alias; $sql[] = '.'; $sql[] = $c2; $sql[] = ']';
                }

            }
        }

        $this->dataSource->setSql(\implode('', $sql));

    }



    /**
     * Invalidates SQL FROM clause
     */
    protected function invalidateQuery()
    {
        $this->dataSource->release();
        $this->isSqlValid = FALSE;
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
        $this->dataSource->select($col, $as);
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
            $relName = $this->reflection->getRelationWith($table->reflection)
                    or function(){throw new Exception("No relation exists between table '" . $this->getName() . "' and '" . $table->getName() . "'.");};
        }

        if (isset($this->joined[$relName])) {
            throw new Exception("Tables '" . $this->getName() . "' and '" . $table->getName() . "' are already joined.");
        }

        if (\in_array($this, $table->joined)) {
            throw new Exception("Circular reference between tables '" . $this->getName() . "' and '" . $table->getName() . "'.");
        }

        $table->setAlias(\implode('', array($this->alias, self::ALIAS_DELIM, $relName)));
        $this->joined[$relName] = $table;

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
        \call_user_func_array(array($this->dataSource, 'where'), $args);
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
        $this->dataSource->orderBy($this->_translateColumns($col), $sorting);
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
        $this->dataSource->applyLimit($limit, $offset);
        return $this;
    }



    /**
     * Returns (and queries) DibiResult.
     * @return DibiResult
     */
    public function getResult()
    {
        $this->buildQuery();
        return $this->dataSource->getResult();
    }



    /**
     * Gets iterator
     * @return Traversable
     */
    public function getIterator()
    {
        $this->buildQuery();
        return $this->dataSource->getIterator();
    }



    /**
     * Generates, executes SQL query and fetches the single row.
     * @return Entity|FALSE  array on success, FALSE if no next record
     */
    public function fetch()
    {
        $this->buildQuery();
        return $this->dataSource->fetch();
    }



    /**
     * Like fetch(), but returns only first field.
     * @return mixed  value on success, FALSE if no next record
     */
    public function fetchSingle()
    {
        $this->buildQuery();
        return $this->dataSource->fetchSingle();
    }



    /**
     * Fetches all records from table.
     * @return array
     */
    public function fetchAll()
    {
        $this->buildQuery();
        return $this->dataSource->fetchAll();
    }



    /**
     * Fetches all records from table and returns associative tree.
     * @param  string  associative descriptor
     * @return array
     */
    public function fetchAssoc($assoc)
    {
        $this->buildQuery();
        return $this->dataSource->fetchAssoc($assoc);
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
        return $this->dataSource->fetchPairs($key, $value);
    }



    /**
     * Returns the number of rows in a given data source.
     * @return int
     */
    public function getTotalCount()
    {
        $this->buildQuery();
        return $this->dataSource->getTotalCount();
    }



    /**
     * Gets number of items
     * @return int
     */
    public function count()
    {
        $this->buildQuery();
        return $this->dataSource->count();
    }



	/**
	 * Dibi interface
	 */
	public function dibi()
	{
		$this->buildQuery();
		$sql = $this->dataSource->getSql();
		$this->dataSource->setSql(array_merge([$sql], func_get_args()));
		$this->isSqlValid = TRUE;
		return $this;
	}



    public function offsetSet($name, $value)
    {
       $this->dataSource->offsetSet($name, $value);
    }



    public function offsetGet($name)
    {
        return $this->dataSource->offsetGet($name);
    }



    public function offsetExists($name)
    {
        return $this->dataSource->offsetExists($name);
    }



    public function offsetUnset($name)
    {
        $this->dataSource->offsetUnset($name);
    }



    /**
     * Should not by called directly
     */
    public function __clone()
    {
        $this->dataSource = clone $this->dataSource;
    }
    
    
    
    public function __destruct()
    {
        unset($this->dataSource);
    }

}
