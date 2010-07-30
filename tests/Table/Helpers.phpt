<?php

/**
 * Test: osto\Table\Helpers common
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto\Table
 * @subpackage UnitTests
 */

use osto\Entity;
use osto\Table;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);


db_connect();


dibi::query('
	CREATE TEMPORARY TABLE `test`.`a` (
	`sid` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`a_a` INT NOT NULL ,
    `a_entity` VARCHAR(255)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`b` (
	`b_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`sid` INT NOT NULL ,
	`b_b` FLOAT NOT NULL ,
	KEY (b_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


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

$a = new A;
$b = new B;

$a->id = 3;
$a->a = 1;
$a->save();
unset($a);

$a = new A;
$a->id = 4;
$a->a = 2;
$a->save();
unset($a);

$b->a = 1;
$b->b = 2;
$b->save();
unset($b);

foreach (A::findAll() as $a) {
    output($a);
}

output(A::find(4));
output(A::findOne('a_a = ', 1));
output(B::findOne('a_a = ', 1));

__halt_compiler();
------EXPECT------
Array
(
    [aid] => 3
    [a] => 1
)


Array
(
    [aid] => 4
    [a] => 2
)


Array
(
    [aid] => 5
    [a] => 1
    [id] => 1
    [b] => 2
    [sid] => 5
)


Array
(
    [aid] => 4
    [a] => 2
)


Array
(
    [aid] => 3
    [a] => 1
)


Array
(
    [aid] => 5
    [a] => 1
    [id] => 1
    [b] => 2
    [sid] => 5
)
