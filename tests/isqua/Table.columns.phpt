<?php

/**
 * Test: isqua\Table columns.
 *
 * @author     Jan Prachař
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

	/**
	 * @null
	 * @column a_main
	 */	
	private $main;
	private $a_alt;
	private $a_a_alt;
	private $t_b;

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

array(5) {
	0 => string(4) "t_id"
	1 => string(4) "main"
	2 => string(5) "a_alt"
	3 => string(7) "a_a_alt"
	4 => string(3) "t_b"
} 