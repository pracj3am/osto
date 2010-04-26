<?php
namespace Test;

/**
 * Test: isqua\Table relations
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
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
}

class C extends Table {
	
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
$a = new A;
dump($a->parents);

output('A children:');
dump($a->children);

output('A singles:');
dump($a->singles);

$d = new D;
output('D foreign keys:');
dump($d->foreign_keys);

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

D foreign keys:

array(3) {
	"ta_id" => string(6) "Test\A"
	"tb_id" => string(6) "Test\B"
	"tc_id" => string(6) "Test\C"
}
