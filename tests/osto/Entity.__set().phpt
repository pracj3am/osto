<?php

/**
 * Test: osto\Entity::__set().
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
 * @property int $rid , column=id, primary_key
 * @property int $a
 * @property B $B , has_many
 * @property C $C , has_one, column=ac
 */
class A extends Entity
{

}

/**
 * @property float $b
 * @property A $A , belongs_to
 */
class B extends Entity
{


}

/**
 * @property int $myid , primary_key
 * @property string $c
 * @property A $A , belongs_to, column=ac
 */
class C extends Entity
{
    public function setC($value)
    {
        $this->_c = $value.'1';
    }

}

$a = new A;

$a->a = 1;
try {
    $a->B = array();
} catch (\Exception $e) {
    dump($e->getMessage());
}
$a->B[] = new B;
try {
    $a->C = new B;
} catch (\Exception $e) {
    dump($e->getMessage());
}
$a->C = new C;

$a->C->_c = 5;
Assert::same($a->C->c, '5');
$a->C->c = 5;
Assert::same($a->C->c, '51');

try {
    $a->x = 5;
} catch (\Exception $e) {
    dump($e->getMessage());
}

dump($a->values);

__halt_compiler();

------EXPECT------
string(48) "Property B must implement interface IDataSource."

string(36) "Property C must be entity of class C"

string(22) "Undeclared property x."

array(4) {
	"rid" => NULL
	"a" => int(1)
	"C" => array(3) {
		"myid" => NULL
		"c" => string(2) "51"
		"ac" => NULL
	}
	"B" => array(1) {
		0 => array(2) {
			"id" => NULL
			"b" => NULL
		}
	}
}