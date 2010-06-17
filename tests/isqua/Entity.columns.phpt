<?php

/**
 * Test: isqua\Entity columns.
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
 * @property string $main, column=a_main
 * @property string $a_alt, column=a_alt
 * @property float $b
 * @property int $t_b
 * @property B $bb, belongs_to
 * @property V $vv, has_many
 */
class Test extends Entity 
{

}

class B extends Entity {
	
}


$t = new Test;

output('Table Test columns:');
dump($t->columns);

__halt_compiler();

------EXPECT------
Table Test columns:

array(6) {
	"id" => string(4) "t_id"
	"main" => string(6) "a_main"
	"a_alt" => string(5) "a_alt"
	"b" => string(3) "t_b"
	"t_b" => string(5) "t_t_b"
	"b_id" => string(4) "b_id"
} 