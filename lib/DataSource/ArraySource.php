<?php
namespace osto\DataSource;


/**
 * Datasource for array data
 *
 */
class ArraySource implements \IDataSource
{

    private $_array;



    public function __construct(array $array)
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

}