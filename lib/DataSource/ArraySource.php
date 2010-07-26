<?php
namespace osto\DataSource;


/**
 * Datasource for array data
 *
 */
class ArraySource implements \IDataSource, \ArrayAccess
{

    private $_array;



    public function __construct(array $array = array())
    {
        $this->_array = $array;
    }



    public function getIterator()
    {
        return new \ArrayIterator($this->_array);
    }



    public function count()
    {
        return count($this->_array);
    }



    public function offsetSet($name, $value)
    {
        if ($name === NULL) {
            $this->_array[] = $value;
        } else {
            $this->_array[$name] = $value;
        }
    }



    public function offsetGet($name)
    {
        return $this->_array[$name];
    }



    public function offsetExists($name)
    {
        return \array_key_exists($name, $this->_array);
    }



    public function offsetUnset($name)
    {
        if (\array_key_exists($name, $this->_values))
            unset($this->_array[$name]);
    }

}