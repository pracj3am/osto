<?php
namespace Test;

/**
 * Test: isqua\Entity relations
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
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

class B extends Entity {
}

class C extends Entity {
	
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
