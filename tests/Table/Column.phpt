<?php

/**
 * Test: osto\Table\Column common
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
    `entity` VARCHAR(255)
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

$a = new Table('A');
$b = new Table('B');


dump($a->a->eq(NULL));
dump($a->a->neq('2'));
dump($a->a->lt(1.2));
dump($a->a->lte(new DateTime('2010-10-10')));
dump($a->a->gt(1));
dump($a->a->gte('aaaa'));

dump($b->b->eq($a->a));
dump($b->a->neq($b->b));

__halt_compiler();
------EXPECT------
array(2) {
	0 => string(8) "[a_a] = "
	1 => NULL
}

array(2) {
	0 => string(9) "[a_a] != "
	1 => string(1) "2"
}

array(2) {
	0 => string(8) "[a_a] < "
	1 => float(1.2)
}

array(2) {
	0 => string(9) "[a_a] <= "
	1 => object(DateTime) (3) {
		"date" => string(19) "2010-10-10 00:00:00"
		"timezone_type" => int(3)
		"timezone" => string(13) "Europe/Prague"
	}
}

array(2) {
	0 => string(8) "[a_a] > "
	1 => int(1)
}

array(2) {
	0 => string(9) "[a_a] >= "
	1 => string(4) "aaaa"
}

array(2) {
	0 => string(10) "[b_b] = %n"
	1 => string(5) "[a_a]"
}

array(2) {
	0 => string(11) "[a_a] != %n"
	1 => string(5) "[b_b]"
}

