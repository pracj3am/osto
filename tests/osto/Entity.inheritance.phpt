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
	`a_entity` VARCHAR(255)
	) ENGINE = InnoDB;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`b` (
	`b_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`b_b` INT NOT NULL ,
	`b_entity` VARCHAR(255) ,
	`a_id` INT NOT NULL ,
	KEY(a_id)
	) ENGINE = InnoDB;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`c` (
	`c_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`c_c` INT NULL ,
	`c_entity` VARCHAR(255) ,
	`a_id2` INT NOT NULL ,
	`b_id` INT NULL ,
	KEY(a_id2) ,
	KEY(b_id)
	) ENGINE = InnoDB;
');

/**
 * @property int $a
 * @property C $C, has_one, column=a_id2
 */
class A extends Entity 
{

}

/**
 * @property int $b
 * @property A $A , belongs_to
 */
class B extends A 
{

}

/**
 * @property int $c
 * @property A $A, belongs_to, column=a_id2
 * @property B $B , belongs_to , null
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
$b->A->a = 8;
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
foreach (A::findAll() as $row) {
	output($row);
}

output('B =====');
foreach (B::findAll() as $row)
	output($row);

output('C =====');
foreach (C::findAll() as $row)
	output($row);


output('Created C =====');
$c = A::create(5);
output($c);

__halt_compiler();

------EXPECT------
A =====

Array
(
    [id] => 1
    [a] => 1
)


Array
(
    [id] => 2
    [a] => 8
)


Array
(
    [id] => 3
    [a] => 2
    [b] => 3
    [a_id] => 3
)


Array
(
    [id] => 4
    [a] => 7
)


Array
(
    [id] => 5
    [a] => 4
    [b] => 5
    [a_id] => 5
    [c] => 6
    [a_id2] => 4
    [b_id] => 5
)


B =====

Array
(
    [id] => 3
    [a] => 2
    [b] => 3
    [a_id] => 3
)


Array
(
    [id] => 5
    [a] => 4
    [b] => 5
    [a_id] => 5
    [c] => 6
    [a_id2] => 4
    [b_id] => 5
)


C =====

Array
(
    [id] => 5
    [a] => 4
    [b] => 5
    [a_id] => 5
    [c] => 6
    [a_id2] => 4
    [b_id] => 5
)


Created C =====

Array
(
    [id] => 5
    [a] => 4
    [b] => 5
    [a_id] => 5
    [c] => 6
    [a_id2] => 4
    [b_id] => 5
) 