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
 * @property C $C , belongs_to
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

/**
 * @property string $c
 * @property A $A , has_many
 */
class C extends Entity
{

}

$a = new Table('A');
$a1 = new Table(new A);
$a2 = new Table(new A);
$b = new Table('B');
$c = new Table('C');

output($a);
output($b);
output($a->where($a->id->eq(1))->select($a->a)->orderBy($a->id));
output($b->where('[:aid:] = ', 1)->select(':a:')->orderBy(':aid:'));
output($a1->join($c));
output($a2->where('[:aid:] = ', 1)->select(':a:')->select(':C.c:')->orderBy(':aid:'));

try {
    $c->join($a1);
} catch (osto\Exception $e) {
    dump($e->getMessage());
}

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
			FROM `a` AS `$a`





			SELECT *
			FROM `b` AS `$b` JOIN (`a` AS `%S%`) ON `$b`.`b_id` = `%S%`.`sid`





			SELECT `a_a`
			FROM `a` AS `$a`
			 WHERE (`sid` =  1)
			 ORDER BY `sid` ASC



			SELECT `$b->extendedEntity`.`a_a`
			FROM `b` AS `$b` JOIN (`a` AS `%S%`) ON `$b`.`b_id` = `%S%`.`sid`
			 WHERE (`$b->extendedEntity`.`sid` =  1)
			 ORDER BY `$b->extendedEntity`.`sid` ASC



			SELECT *
			FROM `a` AS `$a` JOIN (`c` AS `$a->C`) USING (`c_id`)





			SELECT `$a`.`a_a`, `$a->C`.`c_c`
			FROM `a` AS `$a` JOIN (`c` AS `$a->C`) USING (`c_id`)  
			 WHERE (`$a`.`sid` =  1)
			 ORDER BY `$a`.`sid` ASC


string(46) "Circular reference between tables 'c' and 'a'."

string(48) "Can't create reflection for entity 'osto\Entity'"

string(50) "XX is neither entity class name nor entity itself."

string(32) "Undeclared column or property x."

