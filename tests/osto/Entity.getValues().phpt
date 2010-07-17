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