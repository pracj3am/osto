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

	/** @column a_main */
	private $main;
	/** @column a_alt */
	private $a_alt;
	private $b;
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
	"id" => string(4) "t_id"
	"main" => string(6) "a_main"
	"a_alt" => string(5) "a_alt"
	"b" => string(3) "t_b"
	"t_b" => string(5) "t_t_b"
} 