<?php

/**
 * Test: osto\Entity::getValues()
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

use osto\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);


db_connect();



/**
 * @table model_test
 * @prefix a
 * @property string $main, column=a_main, null
 * @property string $alt
 * @property string $a_alt
 * @property string $b
 * @property A $A, has_one
 * @property B $B, has_many
 * @property C $C, belongs_to
 */
class Test extends Entity 
{

}

/**
 * @property int $a
 */
class A extends Entity 
{

}
/**
 * @property int $b
 */
class B extends Entity 
{

}
/**
 * @property int $a
 * @property int $b
 * @property int $c
 */
class C extends Entity 
{

}


$t = new Test;
$t->main = 1;
$t->alt = 2;
$t->a_alt = 3;
$t['a_a_alt'] = 4;
try {
	$t->a_a_alt = 4.5;
} catch (\osto\Exception $e) {
	dump($e->getMessage());
}
$t->b = 5;
$t->A->a = 6;
$t->B[] = new B;
$t->B[0]->b = 7;
$t->C->a = 8;
$t->C->b = 9;

output('Table Test values:');
dump($t->values);

__halt_compiler();

------EXPECT------
string(34) "Undeclared property Test->a_a_alt."


Notice: %a%
Table Test values:

array(9) {
	"id" => NULL
	"main" => string(1) "1"
	"alt" => string(1) "2"
	"a_alt" => string(1) "4"
	"b" => string(1) "5"
	"c_id" => NULL
	"C" => array(4) {
		"id" => NULL
		"a" => int(8)
		"b" => int(9)
		"c" => NULL
	}
	"A" => array(2) {
		"id" => NULL
		"a" => int(6)
	}
	"B" => array(1) {
		0 => array(2) {
			"id" => NULL
			"b" => int(7)
		}
	}
}
