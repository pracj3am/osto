<?php

/**
 * Test: osto\Reflection\Entity serialization
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

use osto\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
\NetteTestHelpers::purge(OSTO_TMP_DIR);


db_connect();


dibi::query('
	CREATE TEMPORARY TABLE `test`.`a` (
	`a_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`a_a` VARCHAR(25) NOT NULL ,
	`b_id` INT NOT NULL ,
	`a_entity` VARCHAR(255)
	) ENGINE = InnoDB;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`a_a` (
	`aa_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`aa_aa` VARCHAR(25) NOT NULL
	) ENGINE = InnoDB;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`b` (
	`b_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`b_b` VARCHAR(25) NOT NULL
	) ENGINE = InnoDB;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`c` (
	`c_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`c_c` VARCHAR(25) NULL ,
	`aa_id` INT NOT NULL ,
	KEY(aa_id)
	) ENGINE = InnoDB;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`d` (
	`d_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`d_d` VARCHAR(25) NULL ,
	`aa_id` INT NOT NULL ,
	KEY(aa_id)
	) ENGINE = InnoDB;
');


/**
 * @property string $a
 * @property B $ab, belongs_to
 */
class A extends Entity 
{

}

/**
 * @property string $aa
 * @property C $ac, has_many, null
 * @property D $ad, has_one
 */
class AA extends A {}

/**
 * @property string $b
 */
class B extends Entity {}
/**
 * @property string $c
 * @property AA $ca, belongs_to
 */
class C extends Entity {}
/**
 * @property string $d
 * @property AA $da, belongs_to
 */
class D extends Entity {}


$aa = new AA;
$aa->a = 'foo';
$aa->aa = 'bar';
$aa->ab->b = 'B';
$aa->ad->d = 'D';
$aa->save();

echo $aa;
$as = serialize($aa);
unset($aa);
$aa = unserialize($as);
echo $aa;

__halt_compiler();

------EXPECT------
Array
(
    [id] => 1
    [a] => foo
    [b_id] => 1
    [ab] => Array
        (
            [id] => 1
            [b] => B
        )

    [aa] => bar
    [ad] => Array
        (
            [id] => 1
            [d] => D
            [aa_id] => 1
        )

)
Array
(
    [id] => 1
    [a] => foo
    [b_id] => 1
    [aa] => bar
)