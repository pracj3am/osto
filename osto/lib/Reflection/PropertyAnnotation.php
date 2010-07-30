<?php

namespace osto\Reflection;

use osto\Nette;



class PropertyAnnotation implements Nette\IAnnotation
{

    public $type;
    public $name;
    public $column;
    public $null = FALSE;
    public $relation = FALSE;
    public $primary_key = FALSE;



    public function __construct(array $values)
    {
        foreach ($values as $k => $v) {
            if ($k === 'column' || $k === 'relation') {
                $this->$k = $v;
            } elseif ($k === 'null' || $v === 'null' || $v === NULL) {
                $this->null = $k === 'null' ? (bool) $v : TRUE;
            } elseif ($v === 'has_one' || $v === 'has_many' || $v === 'belongs_to') {
                $this->relation = $v;
            } elseif ($v === 'primary_key') {
                $this->primary_key = TRUE;
            } elseif (preg_match('/^(' . Nette\AnnotationsParser::RE_IDENTIFIER . ')\s*\$(' . Nette\AnnotationsParser::RE_IDENTIFIER . ')$/i', $v, $matches)) {
                $this->type = in_array($matches[1], array('int', 'integer', 'bool', 'boolean', 'float', 'double', 'string', 'array', 'object', 'null')) ?
                        $matches[1] :
                            class_exists($matches[1]) ?
                            $matches[1] :
                            'uknown type';//@todo throw Exception
                $this->name = $matches[2];
            }
        }
    }

}

