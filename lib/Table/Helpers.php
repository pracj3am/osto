<?php
namespace osto\Table;

use osto\Table;



/**
 * Table query API helpers
 */
class Helpers
{

    /**
     * Finds entity by ID
     * @param Table $table
     * @param int Primary key
     * @return osto\Entity
     */
    public static function find(Table $table, $id)
    {
        return $table->where($table->id->eq($id))->fetch();
    }



    /**
     * Finds entities by condition
     * @param Table $table
     * @param array $cond
     * @return osto\Table
     */
    public static function findAll(Table $table, $cond = NULL)
    {
        $args = \func_get_args();
        unset($args[0]);
        $cond = \is_array($cond) ? $cond : $args;
        return $cond === array() ? $table : $table->where($cond);
    }



    /**
     * Finds a entity by condition
     * @param Table $table
     * @param array $cond
     * @return osto\Entity
     */
    public static function findOne(Table $table, $cond)
    {
        $args = \func_get_args();
        unset($args[0]);
        $cond = \is_array($cond) ? $cond : $args;
        return $table->where($cond)->fetch();
    }

}