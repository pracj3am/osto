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

$a->a = 1;
$b->b = 2;
$a->save();
$b->save();
unset($a, $b);

$a = new Table('A');
$b = new Table('B');

foreach ($a as $aa) {
    dump($aa->values);
}

foreach ($b as $bb) {
    dump($bb->values);
}

$a->release();
$b->release();
dump($b->select(array($b->a, $b->b, $b->id))->fetch()->values);
dump($a->where($a->a->eq(0))->select($a->a)->fetchSingle());

__halt_compiler();
------EXPECT------
array(2) {
	"aid" => int(1)
	"a" => int(1)
}

array(5) {
	"aid" => int(2)
	"a" => int(0)
	"id" => int(1)
	"b" => float(2)
	"sid" => int(2)
}

array(5) {
	"aid" => int(2)
	"a" => int(0)
	"id" => int(1)
	"b" => float(2)
	"sid" => int(2)
}

array(5) {
	"aid" => NULL
	"a" => int(0)
	"id" => int(1)
	"b" => float(2)
	"sid" => NULL
}

string(1) "0"

