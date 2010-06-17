<?php
namespace isqua\Reflection;

use isqua\Nette;



class PropertyAnnotation implements Nette\IAnnotation
{
	
	public $type;
	public $name;
	public $column;
	public $null = FALSE;
	public $relation = FALSE;
			
	public function __construct(array $values) 
	{
		foreach ($values as $k=>$v) {
			if ($k === 'column' || $k === 'relation') {
				$this->$k = $v;
			} elseif ($k === 'null' || $v === 'null' || $v === NULL) {
				$this->null = $k === 'null' ? (bool)$v : TRUE;
			} elseif ($v === 'has_one' || $v === 'has_many' || $v === 'belongs_to') {
				$this->relation = $v;
			} elseif (preg_match('/^('.Nette\AnnotationsParser::RE_IDENTIFIER.')\s*\$('.Nette\AnnotationsParser::RE_IDENTIFIER.')$/i', $v, $matches)) {
				$this->type = $matches[1];
				$this->name = $matches[2];
			}
		}
	}
} 