<?php
namespace osto\DataSource;

use osto;



/**
 * Datasource for database data definition and fetching
 *
 */
class Database extends \DibiDataSource implements \ArrayAccess
{

    private $rowClass = 'DibiRow';
    /** @var \DibiTranslator */
    private $translator;
    /** @var array */
    private $array;


    public function __construct()
    {
        $args = \func_get_args();
        $this->translator = new \DibiTranslator(\dibi::getConnection()->driver);
        parent::__construct($this->translator->translate($args), \dibi::getConnection());
    }



    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;
    }



    /**
     * Returns (and queries) DibiResult.
     * @return \DibiResult
     */
    public function getResult()
    {
        if (strspn($this->sql, '`')===2) {
            throw new osto\Exception("Cannot get result, SQL is empty.");
        }

        $result = parent::getResult();
        $result->setRowClass(array($this->rowClass,'createFromValues'));
        return $result;
    }



    /**
     * @return \Iterator
     */
    public function getIterator()
    {
        if ($this->array !== NULL) {
            return new \ArrayIterator($this->array);
        }

        if (strspn($this->sql, '`')===2) {
            return new \EmptyIterator;
        }

        return parent::getIterator();
    }



    /**
     * Joins table to SQL
     * @param string $sql SQL of joined table
     */
    public function join($sql)
    {
        $this->setSql($this->sql . ' JOIN ' . $sql);
    }



    /**
     * Sets SQL
     * @param string $sql
     */
    public function setSql($sql)
    {
        $this->sql = $this->translator->translate((array)$sql);
        $this->result = $this->count = $this->totalCount = NULL;
    }



    /**
     * Returns SQL
     * @return string SQL
     */
    public function getSql()
    {
        return $this->sql;
    }



    /**
     * Returns the number of rows in a given data source.
     * @return int
     */
    public function count()
    {
        if ($this->count === NULL) {
            if ($this->limit !== NULL || $this->offset) {
                $this->count = parent::count();
            } else {
                $this->count = (int) $this->connection->nativeQuery(
                        \preg_replace('/SELECT\s+(.*?)\s+FROM/', 'SELECT COUNT(*) FROM', $this->__toString())
                )->fetchSingle();
            }
        }
        return $this->count;
    }



    /**
     * Adds conditions to query.
     * @param  mixed  conditions
     * @return Database  provides a fluent interface
     */
    public function where($cond)
    {
        $args = \func_get_args();
        \call_user_func_array(array('parent', 'where'), $args);
        $this->array = NULL;
        return $this;
    }



    /**
     * Selects columns to order by.
     * @param  string|array  column name or array of column names
     * @param  string  		 sorting direction
     * @return Database  provides a fluent interface
     */
    public function orderBy($row, $sorting = 'ASC')
    {
        parent::orderBy($row, $sorting);
        $this->array = NULL;
        return $this;
    }



    /**
     * Limits number of rows.
     * @param  int limit
     * @param  int offset
     * @return Database  provides a fluent interface
     */
    public function applyLimit($limit, $offset = NULL)
    {
        parent::applyLimit($limit, $offset);
        $this->array = NULL;
        return $this;
    }



    /**
     * Clone itself as an array
     * @return array
     */
    public function toArray()
    {
        $a = array();
        foreach ($this as $e) {
            $a[$e->id] = $e;
        }
        return $a;
    }



    public function offsetSet($name, $value)
    {
        if ($this->array === NULL) {
            $this->array = $this->toArray();
        }
        
        if ($name === NULL) {
            $this->array[] = $value;
        } else {
            $this->array[$name] = $value;
        }
    }



    public function offsetGet($name)
    {
        return $this->array[$name];
    }



    public function offsetExists($name)
    {
        return \array_key_exists($name, $this->array);
    }



    public function offsetUnset($name)
    {
        if (\array_key_exists($name, $this->values)) {
            unset($this->array[$name]);
        }
    }

}