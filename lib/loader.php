<?php

function __autoload($class_name)
{
	if (strpos($class_name, 'isqua') === 0)
		require(__DIR__.str_replace('\\','/',substr($class_name,5).'.php'));
}