<?php
namespace Test;

/**
 * Test: isqua\Table\Reflection relations
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;



require __DIR__ . '/../NetteTest/initialize.php';



class A extends Table {
	/** @belongs_to Test\B */
	private $ab;
	/** @has_many %namespace%\C */
	private $ac;
	/** @has_one Test\D */
	private $ad;
}

class B extends Table {
	/** @has_many %namespace%\A */
	private $ba;
	/** @has_many %namespace%\B */
	private $bb;
	/** @has_many %namespace%\C */
	private $bc;
	
}

class C extends Table {
	/** @has_many Test */
	private $x;
	
}

class D extends Table {
	/** @belongs_to %namespace%\A */
	private $x1;
	/** @belongs_to %namespace%\B */
	private $x2;
	/** @belongs_to %namespace%\C */
	private $x3;
	/** @has_many Test\D */
	private $x4;
	
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

output('D foreign keys:');
dump(D::getForeignKeys());

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

D foreign keys:

array(3) {
	"ta_id" => string(6) "Test\A"
	"tb_id" => string(6) "Test\B"
	"tc_id" => string(6) "Test\C"
}
