<?php

require_once 'Exception.php';
require_once 'osto.php';

function __autoload($class_name)
{
	if (strpos($class_name, 'osto') === 0) {
		$file = __DIR__.str_replace('\\','/',substr($class_name,4).'.php');
		if (file_exists($file)) require($file);
	}
}