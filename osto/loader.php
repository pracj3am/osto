<?php

require_once 'lib/Exception.php';
require_once 'osto.php';

function __autoload($class_name)
{
	if (strpos($class_name, 'osto') === 0) {
		$file = __DIR__.'/'.str_replace('\\','/',str_replace('osto', 'lib', $class_name).'.php');
		if (file_exists($file)) require($file);
	}
}