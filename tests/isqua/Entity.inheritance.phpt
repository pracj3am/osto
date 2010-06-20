<?php

/**
 * Test: isqua\Entity inheritance.
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('ISQUA_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(ISQUA_TMP_DIR);


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
foreach (A::getAll() as $row)
	dump($row->values);

output('B =====');
foreach (B::getAll() as $row)
	dump($row->values);

output('C =====');
foreach (C::getAll() as $row)
	dump($row->values);


__halt_compiler();

------EXPECT------
A =====

array(3) {
	"id" => int(1)
	"a" => string(1) "1"
	"entity" => NULL
}

array(3) {
	"id" => int(2)
	"a" => string(1) "2"
	"entity" => string(1) "B"
}

array(3) {
	"id" => int(3)
	"a" => string(1) "7"
	"entity" => NULL
}

array(3) {
	"id" => int(4)
	"a" => string(1) "4"
	"entity" => string(1) "B"
}

B =====

array(5) {
	"id" => int(1)
	"b" => string(1) "3"
	"a_id" => string(1) "2"
	"entity" => NULL
	"ParentEntity" => array(3) {
		"id" => int(1)
		"a" => string(1) "1"
		"entity" => NULL
	}
}

array(5) {
	"id" => int(2)
	"b" => string(1) "5"
	"a_id" => string(1) "4"
	"entity" => string(1) "C"
	"ParentEntity" => array(3) {
		"id" => int(2)
		"a" => string(1) "2"
		"entity" => string(1) "B"
	}
}

C =====

array(5) {
	"id" => int(1)
	"c" => string(1) "6"
	"a_id" => string(1) "3"
	"b_id" => string(1) "2"
	"ParentEntity" => array(5) {
		"id" => int(1)
		"b" => string(1) "3"
		"a_id" => string(1) "2"
		"entity" => NULL
		"ParentEntity" => array(3) {
			"id" => int(1)
			"a" => string(1) "1"
			"entity" => NULL
		}
	}
}