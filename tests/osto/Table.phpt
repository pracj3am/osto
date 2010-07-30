<?php

/**
 * Test: osto\Table common
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

use osto\Entity;
use osto\Table;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);


db_connect();



/**
 * @property int $aid , column=sid, primary_key
 * @property int $a
 */
class A extends Entity
{

}

/**
 * @property float $b
 */
class B extends A
{


}

$a = new Table('A');
$a1 = new Table(new A);
$b = new Table('B');

output($a);
output($b);

try {
    new Table('osto\Entity');
} catch (osto\Exception $e) {
    dump($e->getMessage());
}

try {
    new Table('XX');
} catch (osto\Exception $e) {
    dump($e->getMessage());
}

Assert::same('a', $a->getName());
Assert::same('b', $b->getName());

Assert::same('osto\Table\Column', get_class($a->sid));
Assert::same('osto\Table\Column', get_class($a->aid));
Assert::same('osto\Table\Column', get_class($a->a));
Assert::same('osto\Table\Column', get_class($b->b));
Assert::same('osto\Table\Column', get_class($b->a));
Assert::same('osto\Table\Column', get_class($b->id));

try {
    $b->x;
} catch (osto\Exception $e) {
    dump($e->getMessage());
}

__halt_compiler();
------EXPECT------

			SELECT *
			FROM `a`





			SELECT *
			FROM (SELECT * FROM `b` JOIN `a` USING (`sid`) ) t




string(48) "Can't create reflection for entity 'osto\Entity'"

string(50) "XX is neither entity class name nor entity itself."

string(32) "Undeclared column or property x."
