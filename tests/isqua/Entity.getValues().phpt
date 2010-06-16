<?php

/**
 * Test: isqua\Entity::getValues()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 * @prefix a
 */
class Test extends Entity {

	/**
	 * @null
	 * @column a_main
	 */	
	private $main;
	private $alt;
	private $a_alt;
	private $b;
	
	/** @has_one A */
	private $A;
	/** @has_many B */
	private $B;
	/** @belongs_to C */
	private $C;

}

class A extends Entity {
	private $a;
}
class B extends Entity {
	private $b;
}
class C extends Entity {
	private $a;
	private $b;
	private $c;
}


$t = new Test;
$t->main = 1;
$t->alt = 2;
$t->a_alt = 3;
$t->a_a_alt = 4;
$t->b = 5;
$t->A->a = 6;
$t->B[] = new B;
$t->B->getFirst()->b = 7;
$t->C->a = 8;
$t->C->b = 9;

output('Table Test values:');
dump($t->values);

__halt_compiler();

------EXPECT------
Table Test values:

array(8) {
	"main" => int(1)
	"alt" => int(2)
	"a_alt" => int(4)
	"b" => int(5)
	"c_id" => NULL
	"C" => array(3) {
		"a" => int(8)
		"b" => int(9)
		"c" => NULL
	}
	"B" => array(1) {
		0 => array(1) {
			"b" => int(7)
		}
	}
	"A" => array(1) {
		"a" => int(6)
	}
}