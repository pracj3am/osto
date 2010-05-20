<?php

/**
 * Test initialization and helpers.
 *
 * @author     David Grudl
 * @package    Nette\Test
 */

date_default_timezone_set('Europe/Prague');

require __DIR__ . '/NetteTestCase.php';

require __DIR__ . '/NetteTestHelpers.php';

require __DIR__ . '/Assert.php';

require __DIR__ . '/../../dibi/dibi/dibi.php';



\NetteTestHelpers::startup();



require __DIR__ . '/../../lib/loader.php';


/**
 * Dumps information about a variable in readable format.
 * @param  mixed  variable to dump
 * @param  string
 * @return mixed  variable itself or dump
 */
function dump($var, $message = NULL)
{
	if ($message) {
		echo $message . (preg_match('#[.:?]$#', $message) ? ' ' : ': ');
	}

	\NetteTestHelpers::dump($var, 0);
	echo "\n";
	return $var;
}



/**
 * Writes new message.
 * @param  string
 * @return void
 */
function output($message = NULL)
{
	echo $message ? "$message\n\n" : "===\n\n";
}

function db_connect()
{
	dibi::connect('driver=mysqli&host=localhost&username=root&charset=utf8');
	dibi::query('CREATE DATABASE IF NOT EXISTS test');
	dibi::query('USE test');
}