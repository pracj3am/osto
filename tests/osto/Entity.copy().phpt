<?php

/**
 * Test: osto\Entity::copy().
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
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`a_a` INT NOT NULL
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`b` (
	`b_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`id` INT NOT NULL ,
	`b_b` FLOAT NOT NULL DEFAULT "3.14",
	KEY (b_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`c` (
	`c_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`ac` INT NULL ,
	`c_c` VARCHAR(2048) NOT NULL DEFAULT "-",
	KEY (ac)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


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

}

$a = new A;

$a->a = 1;
$a->B[] = new B;
$a->C->c = 3;
$a->save();

$a1 = $a->copy();

dump(@array_diff_assoc($a->values, $a1->values));

$a2 = new A(1);
$a2->load(1);
$a3 = $a2->copy();

dump(@array_diff_assoc($a2->values, $a3->values));

__halt_compiler();

------EXPECT------
array(1) {
	"rid" => int(1)
}

array(1) {
	"rid" => int(1)
}
