<?php

/**
 * Test: osto\Entity loading methods
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
$id = $a->id;
unset($a);

$a = new A($id);
$a->load(3);

dump($a->values);
unset($a);

$a = new A($id);
$a->loadChildren('B', 2);
dump($a->values);
unset($a);

$a = new A($id);
$a->loadSingles();
dump($a->values);
$a->C->loadParents('A', 1);
dump($a->values);

__halt_compiler();

------EXPECT------
array(4) {
	"rid" => int(1)
	"a" => int(1)
	"C" => array(4) {
		"myid" => int(1)
		"c" => string(1) "3"
		"ac" => int(1)
		"A" => array(4) {
			"rid" => int(1)
			"a" => int(1)
			"C" => array(3) {
				"myid" => int(1)
				"c" => string(1) "3"
				"ac" => int(1)
			}
			"B" => array(1) {
				0 => array(2) {
					"id" => int(1)
					"b" => float(3.14)
				}
			}
		}
	}
	"B" => array(1) {
		0 => array(2) {
			"id" => int(1)
			"b" => float(3.14)
		}
	}
}

array(3) {
	"rid" => int(1)
	"a" => NULL
	"B" => array(1) {
		0 => array(2) {
			"id" => int(1)
			"b" => float(3.14)
		}
	}
}

array(3) {
	"rid" => int(1)
	"a" => NULL
	"C" => array(3) {
		"myid" => int(1)
		"c" => string(1) "3"
		"ac" => int(1)
	}
}

array(3) {
	"rid" => int(1)
	"a" => NULL
	"C" => array(4) {
		"myid" => int(1)
		"c" => string(1) "3"
		"ac" => int(1)
		"A" => array(4) {
			"rid" => int(1)
			"a" => int(1)
			"C" => array(3) {
				"myid" => int(1)
				"c" => string(1) "3"
				"ac" => int(1)
			}
			"B" => array(1) {
				0 => array(2) {
					"id" => int(1)
					"b" => float(3.14)
				}
			}
		}
	}
}
 