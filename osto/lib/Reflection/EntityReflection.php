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
 * @property-read string $name
 * @property-read array $children
 * @property-read array $parents
 * @property-read array $singles
 * @property-read array $columns
 * @property-read array $types
 * @property-read array $foreignKeys
 * @property-read string $primaryKey
 * @property-read string $primaryKeyColumn
 * @property-read string $tableName
 * @property-read string $prefix
 * @property-read string $parentEntity
 * @property-read string $entityColumn
 * @method bool isColumn(string $name)
 * @method bool isNullColumn(string $name)
 * @method string getForeignKeyName(string $name)
 * @method bool isExtendingEntity()
 * @method string getRelationWith(string|osto\Entity|osto\Reflection\EntityReflection $reflection)
 */
final class EntityReflection
{

    const ID = 'id';
    const ENTITY_COLUMN = 'entity';


    protected $children = array();
    protected $parents = array();
    protected $singles = array();
    protected $columns = array();
    protected $types = array();
    protected $foreign_keys = array();
    protected $primary_key;
    private $_cache;
    private $_properties;
    private $_prefix;
    private $_tableName;

    /**
     * @var \ReflectionClass
     */
    private $_reflection;
    private $_class;



    /**
     * @todo vyjímku pro dva sloupce s stejným názvem, vč PK!
     */
    public function __construct($argument)
    {
        $this->_class = $argument;

        if (!$this->_isEntity()) {
            throw new Exception("Cannot create reflection: {$this->name} is not an entity.");
        }

        $default_primary_key =  $this->prefix . '_' . self::ID;

        $this->_properties = $this->getAnnotations('property');

        foreach ($this->_properties as &$pa) {
            if ($pa->primary_key) {
                $this->primary_key = $pa->name;
                $this->columns[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $default_primary_key;
                $this->types[$this->columns[$pa->name]] = $pa->type;
            }
        }

        if ($this->primary_key === NULL) {
            $this->primary_key = self::ID;
            $this->columns[self::ID] = $default_primary_key;
            $this->types[$this->columns[self::ID]] = 'int';
        }

        foreach ($this->_properties as &$pa) {
            if ($pa->primary_key) {
                continue;

            } elseif ($pa->relation === 'belongs_to') {
                $parentClass = $pa->type;
                if ($parentClass == $this->name) {
                    $pr = $this;
                } else {
                    $pr = &$parentClass::getReflection();
                }
                \is_string($pa->column) or
                        $pa->column = $pr->getPrimaryKeyColumn();

                $this->parents[$pa->name] = $parentClass;
                $this->columns[$pa->name] = $pa->column;
                $this->types[$this->columns[$pa->name]] = $pr->types[$pr->getPrimaryKeyColumn()];

            } elseif ($pa->relation === 'has_many') {
                $this->children[$pa->name] = $pa->type;
                $this->foreign_keys[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $this->columns[$this->primary_key];

            } elseif ($pa->relation === 'has_one') {
                $this->singles[$pa->name] = $pa->type;
                $this->foreign_keys[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        $this->columns[$this->primary_key];

            } elseif ($pa->relation === FALSE) {
                $this->columns[$pa->name] = \is_string($pa->column) ?
                        $pa->column :
                        ($pa->column = $this->prefix . '_' . $pa->name);
                $this->types[$this->columns[$pa->name]] = $pa->type;
            }
        }

        if ($this->_isExtendingEntity()) {
            $parentEntity = $this->_getParentEntity();
            $pr = &$parentEntity::getReflection();
            $this->parents[Entity::EXTENDED] = $parentEntity;
            $this->columns[Entity::EXTENDED] = $this->getPrimaryKeyColumn();
            $this->types[$this->columns[Entity::EXTENDED]] = $pr->types[$pr->getPrimaryKeyColumn()];
        }
    }



    public function __get($name)
    {
        return $this->__call(Helpers::getter($name), array());
    }



    public function __isset($name)
    {
        return \method_exists($this, Helpers::getter($name)) || \method_exists($this->getReflection(), Helpers::getter($name));
    }



    public function __call($name, $arguments)
    {
        //caching results of static methods
        if (\method_exists($target = $this, $name) || \method_exists($target = $this, $name = ltrim($name, '_')) || \method_exists($target = $this->getReflection(), $name)) {
            $cachePath = array($name, md5(\serialize($arguments)));
            $cache = & $this->getCache($cachePath);
            if ($cache === array()) {
                $cache = \call_user_func_array(array($target, $name), $arguments);
            }
            return $cache;
        }

    }



    private function &getCache($cachePath = NULL)
    {
        $cache = & $this->_cache;
        if (is_array($cachePath)) {
            foreach ($cachePath as $part) {
                if (!isset($cache[$part]))
                    $cache[$part] = array();

                $cache = & $cache[$part];
            }
        }
        return $cache;
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
        $res = $this->_getAllAnnotations();
        return isset($res[$name]) ? $res[$name] : array();
    }



    private function getAnnotation($name)
    {
        $res = $this->_getAllAnnotations();
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
        $res = $this->_getPropertyAnnotations($prop);
        return isset($res[$name]) ? end($res[$name]) : NULL;
    }



    private function getForeignKeyName($name)
    {
        return isset($this->foreign_keys[$name]) ? $this->foreign_keys[$name] :
                FALSE;
    }



    private function isEntity()
    {
        return $this->getReflection()->isSubClassOf('osto\Entity') && !$this->getReflection()->isAbstract();
    }



    private function isExtendingEntity()
    {
        $parentEntity = $this->_getParentEntity();
        if (!$parentEntity || !$this->_isEntity()) {
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
        return $this->_prefix . '_' . self::ENTITY_COLUMN;
    }



    public function getTableName()
    {
        if (!isset($this->_tableName)) {
            if (($tn = $this->getAnnotation('table')) && \is_string($tn))
                $this->_tableName = $tn;
            else
                $this->_tableName = Helpers::fromCamelCase(strrpos($this->name, '\\') !== FALSE ? \substr($this->name, \strrpos($this->name, '\\') + 1) : $this->name);
        }

        return $this->_tableName;
    }



    public function getPrefix()
    {
        if (!isset($this->_prefix)) {
            if (($prefix = $this->getAnnotation('prefix')) && \is_string($prefix))
                $this->_prefix = $prefix;
            else
                $this->_prefix = \strtolower(\preg_replace('/[^A-Z0-9]*/', '', $this->name));
        }

        return $this->_prefix;
    }



    public function getParents()
    {
        return $this->parents;
    }



    public function getChildren()
    {
        return $this->children;
    }



    public function getSingles()
    {
        return $this->singles;
    }



    public function getColumns()
    {
        return $this->columns;
    }



    public function isColumn($name)
    {
        return \in_array($name, $this->columns, TRUE);
    }



    public function isProperty($name)
    {
        return \array_key_exists($name, $this->columns);
    }



    public function getTypes()
    {
        return $this->types;
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



    public function getPrimaryKey()
    {
        return $this->primary_key;
    }



    public function getPrimaryKeyColumn()
    {
        return $this->columns[$this->primary_key];
    }



    public function isSelfReferencing()
    {
        return \in_array($this->name, $this->parents) && \in_array($this->name, $this->children);
    }



    /**
     * If reflected entity has relation with another entity, returns relation name
     * @param mixed $reflection EntityReflection or Entity
     * @return bool|string
     * @throws osto\Exception
     */
    private function getRelationWith($reflection)
    {
        if (!$reflection instanceof EntityReflection) {
            try {
                $reflection = $reflection::getReflection();
            } catch (\Exception $e) {
                throw new Exception("Unable to get EntityReflection from '$reflection'", 0, $e);
            }
        }

        if ($name = \array_search($reflection->name, $this->parents, TRUE)) {
            return $name;
        }

        if ($name = \array_search($reflection->name, $this->singles, TRUE)) {
            return $name;
        }

        if ($name = \array_search($reflection->name, $this->children, TRUE)) {
            return $name;
        }

        return FALSE;
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
            if (!isset($cache[$this->name])) {
                $cache->save($this->name, \serialize($this), array(
                    Caching\Cache::FILES => array($this->getFileName())
                ));
            }
        } catch (\Exception $e) {
            \error_log($e->getMessage());
        }
    }

}

