<?php
namespace Test;

/**
 * Test: isqua\Reflection\EntityReflection relations
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';


/**
 * @property Test\B $ab, belongs_to
 * @property Test\C $ac, has_many
 * @property Test\D $ad, has_one
 */
class A extends Entity 
{

}

/**
 * @property Test\A $ba, has_many
 * @property Test\B $bb, has_many
 * @property Test\C $bc, has_many
 */
class B extends Entity 
{
	
}

/**
 * @property Test $x, has_many
 */
class C extends Entity {
	/** @has_many Test */
	private $x;
	
}

/**
 * @property Test\A $x1, belongs_to
 * @property Test\B $x2, belongs_to
 * @property Test\C $x3, belongs_to
 * @property Test\D $x4, has_many
 */
class D extends Entity 
{
	
}

output('A parents:');
dump(A::getParents());

output('A children:');
dump(A::getChildren());

output('A singles:');
dump(A::getSingles());

output('B children:');
dump(B::getChildren());

output('C children:');
dump(C::getChildren());

output('D columns:');
dump(D::getColumns());


__halt_compiler();

------EXPECT------
A parents:

array(1) {
	"ab" => string(6) "Test\B"
}

A children:

array(1) {
	"ac" => string(6) "Test\C"
}

A singles:

array(1) {
	"ad" => string(6) "Test\D"
}

B children:

array(3) {
	"ba" => string(6) "Test\A"
	"bb" => string(6) "Test\B"
	"bc" => string(6) "Test\C"
}

C children:

array(1) {
	"x" => string(4) "Test"
}

D columns:

array(4) {
	"id" => string(5) "td_id"
	"ta_id" => string(5) "ta_id"
	"tb_id" => string(5) "tb_id"
	"tc_id" => string(5) "tc_id"
}
