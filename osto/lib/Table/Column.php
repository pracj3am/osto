<?php
namespace osto\Table;

use osto\Table;



/**
 * Column for table query API
 *
 * @author Jan Prachař <jan.prachar@gmail.com>
 */
class Column
{
    
    /**
     * Table, the column belongs to
     * @var osto\Table
     */
    public $table;

    /**
     * Column name
     * @var string
     */
    public $name;



    /**
     * Constructor
     * @param osto\Table $table
     * @param string $name
     */
    public function __construct(Table $table, $name)
    {
        $this->table = $table;
        $this->name = $name;
    }



    /**
     * Binary operator condition
     * @param string $operator
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    private function _op($operator, $operand)
    {
        $cond = array('['.$this.']', $operator);
        if ($operand instanceof self) {
            $cond[] = '%n';
            $operand = (string)$operand;
        } elseif ($operand instanceof \DateTime) {
            $cond[] = '%d';
        } elseif (\is_int($operand)) {
            $cond[] = '%i';
        } elseif (\is_float ($operand)) {
            $cond[] = '%f';
        } else {
            $cond[] = '%s';
        }

        return array(\implode(' ', $cond), $operand);
    }



    /**
     * Defines equality condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function eq($operand)
    {
        return $this->_op('=', $operand);
    }



    /**
     * Defines inequality condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function neq($operand)
    {
        return $this->_op('!=', $operand);
    }



    /**
     * Defines lower than condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function lt($operand)
    {
        return $this->_op('<', $operand);
    }



    /**
     * Defines lower than or equal condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function lte($operand)
    {
        return $this->_op('<=', $operand);
    }



    /**
     * Defines greater than condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function gt($operand)
    {
        return $this->_op('>', $operand);
    }



    /**
     * Defines greater than or equal condition
     * @param mixed $operand
     * @return array SQL condition in dibi syntax
     */
    public function gte($operand)
    {
        return $this->_op('>=', $operand);
    }



    public function __toString()
    {
        return $this->name;
    }
}