<?php

/**
 * Test: isqua\Table columns.
 *
 * @author     Jan Prachaø
 * @category   isqua
 * @package    isqua
 * @subpackage UnitTests
 */

use isqua\Table;



require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 */
class Test extends Table {
	static $PREFIX = 'a';

	/**
	 * @null
	 * @column a_main
	 */	
	private $main;
	private $a_alt;

	static $PARENTS = array();
	static $CHILDREN = array();
	static $NULL_COLUMNS = array();
}


$t = new Test;

output('Table Test columns:');
dump($t->columns);

__halt_compiler();

------EXPECT------
Table Test columns:

array(3) {
	0 => string(4) "a_id"
	1 => string(4) "main"
	2 => string(5) "a_alt"
} 