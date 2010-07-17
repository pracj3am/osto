<?php
namespace Test;

/**
 * Test: osto\Entity relations
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

use osto\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
\NetteTestHelpers::purge(OSTO_TMP_DIR);



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


output('A parents:');
$a = new A;
dump($a->parents);

output('A children:');
dump($a->children);

output('A singles:');
dump($a->singles);


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
