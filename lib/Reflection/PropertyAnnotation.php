<?php
namespace isqua\Reflection;

use isqua\Nette;



class PropertyAnnotation implements Nette\IAnnotation
{
	
	public $type;
	public $name;
	public $column;
	public $null = FALSE;
	public $relation;
			
	public function __construct(array $values) 
	{
		foreach ($values as $k=>$v) {
			if ($k === 'column' || $k === 'null' || $k === 'relation') {
				$this->$k = $v;
			} elseif (preg_match('/^('.Nette\AnnotationsParser::RE_IDENTIFIER.')\s*\$('.Nette\AnnotationsParser::RE_IDENTIFIER.')$/i', $v, $matches)) {
				$this->type = $matches[1];
				$this->name = $matches[2];
			}
		}
	}
} 