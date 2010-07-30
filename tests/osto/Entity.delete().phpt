<?php

/**
 * Test: osto\Entity::delete().
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
	`b_b` FLOAT NOT NULL DEFAULT "3.14",
	KEY (b_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


/**
 * @property int $rid , column=sid, primary_key
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
$a->a = 1;
$a->save();
$a_id = $a->id;
unset($a);

$b = new B;
$b->a = 2;
$b->b = 3;
$b->save();
$b_id = $b->b_id;
unset($b);

$a = new A($a_id);
$a->delete();
unset($a);

$b = new B($b_id);
$b->load();
$b->delete();

$a = new A($a_id);
$b = new B($b_id);

Assert::false($a->load());
Assert::false($b->load());
Assert::same(0, A::findAll()->count());
