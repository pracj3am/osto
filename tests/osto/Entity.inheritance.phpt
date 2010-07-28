<?php

/**
 * Test: osto\Entity inheritance.
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
	`a_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`a_a` INT NOT NULL ,
	`entity` VARCHAR(255)
	) ENGINE = InnoDB;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`b` (
	`b_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`b_b` INT NOT NULL ,
	`entity` VARCHAR(255) ,
	`a_id` INT NOT NULL ,
	KEY(a_id)
	) ENGINE = InnoDB;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`c` (
	`c_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`c_c` INT NULL ,
	`entity` VARCHAR(255) ,
	`a_id` INT NOT NULL ,
	`b_id` INT NOT NULL ,
	KEY(a_id) ,
	KEY(b_id)
	) ENGINE = InnoDB;
');

/**
 * @property int $a
 * @property C $C, has_one
 */
class A extends Entity 
{

}

/**
 * @property int $b
 */
class B extends A 
{

}

/**
 * @property int $c
 * @property A $A, belongs_to
 */
class C extends B 
{

}

$a = new A;

$a->a = 1;
$a->save();
unset($a);

$b = new B;

$b->a = 2;
$b->b = 3;
$b->save();
unset($b);

$c = new C;

$c->a = 4;
$c->b = 5;
$c->c = 6;
$c->A->a = 7;
$c->save();
unset($c);

output('A =====');
foreach (A::findAll() as $row)
	dump($row->values);

output('B =====');
foreach (B::findAll() as $row)
	dump($row->values);

output('C =====');
foreach (C::findAll() as $row)
	dump($row->values);


__halt_compiler();

------EXPECT------
A =====

array(2) {
	"id" => int(1)
	"a" => int(1)
}

array(4) {
	"id" => int(2)
	"a" => int(2)
	"b" => int(3)
	"a_id" => int(2)
}

array(2) {
	"id" => int(3)
	"a" => int(7)
}

array(6) {
	"id" => int(4)
	"a" => int(4)
	"b" => int(5)
	"a_id" => int(4)
	"c" => int(6)
	"b_id" => int(2)
}

B =====

array(4) {
	"id" => int(2)
	"a" => int(2)
	"b" => int(3)
	"a_id" => int(2)
}

array(6) {
	"id" => int(4)
	"a" => int(4)
	"b" => int(5)
	"a_id" => int(4)
	"c" => int(6)
	"b_id" => int(2)
}

C =====

array(6) {
	"id" => int(4)
	"a" => int(4)
	"b" => int(5)
	"a_id" => int(4)
	"c" => int(6)
	"b_id" => int(2)
}
