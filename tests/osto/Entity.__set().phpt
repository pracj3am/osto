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

    public function getMaslo()
    {
        
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

echo $a;

Assert::true(isset($a->id));
Assert::true(isset($a->rid));
Assert::true(isset($a->B));
Assert::true(isset($a->C));
Assert::true(isset($a->C->c));
Assert::true(isset($a->C->maslo));
Assert::false(isset($a->C->A));
Assert::false(isset($a->x));

$a->B = NULL;
$a->C = NULL;

echo $a;

__halt_compiler();

------EXPECT------
string(48) "Property B must implement interface IDataSource."

string(36) "Property C must be entity of class C"

string(25) "Undeclared property A->x."

Array
(
    [rid] => 
    [a] => 1
    [C] => Array
        (
            [myid] => 
            [c] => 51
            [ac] => 
        )

    [B] => Array
        (
            [0] => Array
                (
                    [id] => 
                    [b] => 
                )

        )

)
Array
(
    [rid] => 
    [a] => 1
)