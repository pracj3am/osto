<?php

namespace isqua\Table;



abstract class Helpers 
{

	public static function fromCamelCase($name) {
		return strtolower(preg_replace('/(?<=[^_])([A-Z])/', '_\1', $name));
	}
	
	public static function toCamelCase($name) {
		return preg_replace_callback('/(?<=[^_])_([^_])/', function($matches){return strtoupper($matches[1]);}, $name);
	}
	
} 