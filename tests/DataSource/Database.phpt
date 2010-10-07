<?php

/**
 * Test: osto\DataSource\Database common
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto\DataSource
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

$a->a = 1;
$b->b = 2;
$a->save();
$b->save();
unset($a, $b);

$a = new Table('A');
$b = new Table('B');

foreach ($a as $aa) {
    output($aa);
}

foreach ($b as $bb) {
    output($bb);
}

$a->release();
$b->release();
output($b->select(array($b->a, $b->id))->fetch());
dump($a->where($a->a->eq(0))->select($a->a)->fetchSingle());
dump($a->where(':a: = ', 0)->select(':a:')->fetchSingle());

__halt_compiler();
------EXPECT------
Array
(
    [aid] => 1
    [a] => 1
)


Array
(
    [aid] => 2
    [a] => 0
    [id] => 2
    [b] => 2
)


Array
(
    [aid] => 2
    [a] => 0
    [id] => 2
    [b] => 2
)


Array
(
    [aid] => 2
    [a] => 0
    [id] => 2
    [b] =>
)


string(1) "0"

string(1) "0"

