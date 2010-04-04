<?php

/**
 * Test: isqua\Table::getValues()
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
 * @prefix a
 */
class Test extends Table {

	/**
	 * @null
	 * @column a_main
	 */	
	private $main;
	private $a_alt;
	private $a_a_alt;
	private $a_b;

	static $PARENTS = array();
	static $CHILDREN = array();
	static $NULL_COLUMNS = array();
}


$t = new Test;
$t->main = 1;
$t->alt = 2;
$t->a_alt = 3;
$t->a_a_alt = 4;
$t->b = 5;

output('Table Test values:');
dump($t->values);

__halt_compiler();

------EXPECT------
Table Test values:

array(4) {
	"main" => int(1)
	"alt" => int(3)
	"a_a_alt" => int(4)
	"b" => int(5)
}