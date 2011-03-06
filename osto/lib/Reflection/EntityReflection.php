<?php

namespace osto\Reflection;

use osto\Entity;
use osto\Helpers;
use osto\Table;
use osto\Exception;
use osto\Nette\AnnotationsParser;
use osto\Nette\Caching;


if (!defined('OSTO_TMP_DIR') && defined('TMP_DIR')) {
    define('OSTO_TMP_DIR', TMP_DIR);
}



/**
 * @method bool isNullColumn(string $name)
 * @method string getName()
 * @method string getParentEntity()
 * @method string getForeignKeyName(string $name)
 * @method bool isEntity()
 * @method bool isExtendingEntity()
 */
final class EntityReflection
{

    const ID = 'id';
    const ENTITY_COLUMN = 'entity';


    /** @var array */
    public $children = array();
    /** @var array */
    public $parents = array();
    /** @var array */
    public $singles = array();
    /** @var array */
    public $columns = array();
    /** @var array */
    public $types = array();
    /** @var array */
    public $foreignKeys = array();

    /** @var string */
    public $primaryKey;
    /** @var string */
    public $tableName;
    /** @var string */
    public $prefix;

    /** @var array */
    private $cache = array();
    
    private $_properties;

    /**
     * @var \ReflectionClass
     */
    private $_reflection;
    /** @var string */
    private $_class;



    /**
     * @todo vyjímku pro dva sloupce s stejným názvem, vč PK!
     */
    public function __construct($argument)
    {
        $this->_class = $argument;

        if (!$this->__call('isEntity')) {
            throw new Exception("Cannot create reflection: '$argument' is not an entity.");
        }


        if (($prefix = $this->getAnnotation('prefix')) && \is_string($prefix)) {
            $this->prefix = $prefix;
        } else {
            $this->prefix = \strtolower(\preg_replace('/[^A-Z0-9]*/', '', $this->getName()));
        }


        $default_primary_key =  $this->prefix . '_' . self::ID;

        $this->_properties = $this->getAnnotations('property');

        foreach ($this->_properties as &$pa) {
            if ($pa->primary_key) {
                $this->primaryKey = $pa->name;
                $this->columns[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $default_primary_key;
                $this->types[$this->columns[$pa->name]] = $pa->type;
            }
        }

        if ($this->primaryKey === NULL) {
            $this->primaryKey = self::ID;
            $this->columns[self::ID] = $default_primary_key;
            $this->types[$this->columns[self::ID]] = 'int';
        }

        foreach ($this->_properties as &$pa) {
            if ($pa->primary_key) {
                continue;

            } elseif ($pa->relation === 'belongs_to') {
                $parentClass = $pa->type;
                if ($parentClass == $this->getName()) {
                    $pr = $this;
                } else {
                    $pr = $parentClass::getReflection();
                }
                \is_string($pa->column) or
                        $pa->column = $pr->getPrimaryKeyColumn();

                $this->parents[$pa->name] = $parentClass;
                $this->columns[$pa->name] = $pa->column;
                $this->types[$this->columns[$pa->name]] = $pr->types[$pr->getPrimaryKeyColumn()];

            } elseif ($pa->relation === 'has_many') {
                $this->children[$pa->name] = $pa->type;
                $this->foreignKeys[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $this->columns[$this->primaryKey];

            } elseif ($pa->relation === 'has_one') {
                $this->singles[$pa->name] = $pa->type;
                $this->foreignKeys[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $this->columns[$this->primaryKey];

            } elseif ($pa->relation === FALSE) {
                $this->columns[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        ($pa->column = $this->prefix . '_' . $pa->name);
                $this->types[$this->columns[$pa->name]] = $pa->type;
            }
        }

        if ($this->__call('isExtendingEntity')) {
            $parentEntity = $this->__call('getParentEntity');
            $pr = $parentEntity::getReflection();
            $this->parents[Entity::EXTENDED] = $parentEntity;
            $this->columns[Entity::EXTENDED] = $this->getPrimaryKeyColumn();
            $this->types[$this->columns[Entity::EXTENDED]] = $pr->types[$pr->getPrimaryKeyColumn()];
        }

        
        if (($tn = $this->getAnnotation('table')) && \is_string($tn)) {
            $this->tableName = $tn;
        } else {
            $this->tableName = Helpers::fromCamelCase(strrpos($this->getName(), '\\') !== FALSE ? \substr($this->getName(), \strrpos($this->getName(), '\\') + 1) : $this->getName());
        }


    }



    public function __call($name, $arguments = array())
    {
        //caching results of methods call
        if (\method_exists($target = $this, $name) || \method_exists($target = $this->getReflection(), $name)) {
            $key = $name . ($arguments ? '|' . $arguments[0] . (isset($arguments[1]) ? ',' . $arguments[1] . (isset($arguments[2]) ? ',' . $arguments[2] : '') : '') : '');
            if (!isset($this->cache[$key])) {
                $this->cache[$key] = \call_user_func_array(array($target, $name), $arguments);
            }
            return $this->cache[$key];
        }

    }


    public function getTableName()
    {
        return $this->tableName;
    }


    public function getPrefix()
    {
        return $this->prefix;
    }



    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }



    public function getReflection()
    {
        if (!isset($this->_reflection)) {
            $this->_reflection = new \ReflectionClass($this->_class);
        }
        return $this->_reflection;
    }



    private function getName()
    {
        return $this->getReflection()->name;
    }



    private function getAllAnnotations()
    {
        return AnnotationsParser::getAll($this->getReflection());
    }



    private function getAnnotations($name)
    {
        $res = $this->__call('getAllAnnotations');
        return isset($res[$name]) ? $res[$name] : array();
    }



    private function getAnnotation($name)
    {
        $res = $this->__call('getAllAnnotations');
        return isset($res[$name]) ? end($res[$name]) : NULL;
    }



    private function getPropertyAnnotations($prop)
    {
        if ($prop instanceof \ReflectionProperty) {
            $rp = $prop;
        } else {
            $rp = $this->getProperty($prop);
        }

        return AnnotationsParser::getAll($rp);
    }



    private function getPropertyAnnotation($prop, $name)
    {
        $res = $this->__call('getPropertyAnnotations', array($prop));
        return isset($res[$name]) ? end($res[$name]) : NULL;
    }



    private function getForeignKeyName($name)
    {
        return isset($this->foreignKeys[$name]) ? $this->foreignKeys[$name] :
                FALSE;
    }



    private function isEntity()
    {
        return $this->getReflection()->isSubClassOf('osto\Entity') && !$this->getReflection()->isAbstract();
    }



    private function isExtendingEntity()
    {
        $parentEntity = $this->__call('getParentEntity');
        if (!$parentEntity || !$this->__call('isEntity')) {
            return FALSE;
        }

        try {
            $r = $parentEntity::getReflection();
        } catch (Exception $e) {
            return FALSE;
        }

        return $r->isEntity();
    }



    private function getParentEntity()
    {
        return ($pc = $this->getReflection()->getParentClass()) ? $pc->name : FALSE;
    }



    public function getEntityColumn()
    {
        return $this->prefix . '_' . self::ENTITY_COLUMN;
    }



    public function isColumn($name)
    {
        return \in_array($name, $this->columns, TRUE);
    }



    public function isProperty($name)
    {
        return \array_key_exists($name, $this->columns);
    }



    private function isNullColumn($name)
    {
        if ($name === self::ENTITY_COLUMN)
            return TRUE;

        foreach ($this->_properties as $pa)
            if ($pa->column === $name)
                return $pa->null;

        foreach ($this->_properties as $pa)
            if ($pa->name === $name)
                return $pa->null;

        return FALSE;
    }



    public function getPrimaryKeyColumn()
    {
        return $this->columns[$this->primaryKey];
    }



    public function isSelfReferencing()
    {
        return \in_array($this->getName(), $this->parents) && \in_array($this->getName(), $this->children);
    }



    /**
     * If reflected entity has relation with another entity, returns relation name
     * @param mixed $reflection EntityReflection or Entity
     * @return bool|string
     * @throws osto\Exception
     */
    public function getRelationWith($reflection)
    {
        if (!$reflection instanceof EntityReflection) {
            try {
                $reflection = $reflection::getReflection();
            } catch (\Exception $e) {
                throw new Exception("Unable to get EntityReflection from '$reflection'", 0, $e);
            }
        }

        if ($name = \array_search($reflection->getName(), $this->parents, TRUE)) {
            return $name;
        }

        if ($name = \array_search($reflection->getName(), $this->singles, TRUE)) {
            return $name;
        }

        if ($name = \array_search($reflection->getName(), $this->children, TRUE)) {
            return $name;
        }

        return FALSE;
    }



    public function getColumns()
    {
        return $this->columns;
    }



    public function getChildren()
    {
        return $this->children;
    }



    public function getParents()
    {
        return $this->parents;
    }



    public function getSingles()
    {
        return $this->singles;
    }



    public static function instantiateCache()
    {
        $cacheStorage = new Caching\FileStorage(OSTO_TMP_DIR);
        return new Caching\Cache($cacheStorage, 'Osto.EntityReflection');
    }



    public static function create($entityClass)
    {
        $cache = self::instantiateCache();
        if (isset($cache[$entityClass])) {
            $r = @\unserialize($cache[$entityClass]);
        } else {
            $r = new self($entityClass);
        }

        return $r;
    }



    public function __sleep()
    {
        unset($this->_reflection);
        return \array_keys(\get_object_vars($this));
    }



    public function __destruct()
    {
        try {
            $cache = self::instantiateCache();
            if (!isset($cache[$this->getName()])) {
                $cache->save($this->getName(), \serialize($this), array(
                    Caching\Cache::FILES => array($this->getFileName())
                ));
            }
        } catch (\Exception $e) {
            \error_log($e->getMessage());
        }
    }

}

