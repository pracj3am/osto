<?php

/**
 * Test: osto\Entity::__get().
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
    public function getC()
    {
        return $this->_c.'1';
    }

}

$a = new A;

$a->a = 1;
$a->B[] = new B;
$a->C->c = 3;
$a->save();
$id = $a->id;
unset($a);

$a = new A($id);

Assert::same($a->id, $a->rid);
Assert::null($a->_B);
Assert::null($a->_C);

output('A->B');
foreach ($a->B as $b) {
    dump($b->values);
}

output('A->C');
dump($a->C->values);

output('A->C->A');
dump($a->C->A->values);

$a->load();
Assert::same($a->a, 1);
Assert::same($a->C->_c, '3');
Assert::same($a->C->c, '31');

try {
    $a->x;
} catch (\Exception $e) {
    dump($e->getMessage());
}

__halt_compiler();

------EXPECT------
A->B

array(2) {
	"id" => int(1)
	"b" => float(3.14)
}

A->C

array(3) {
	"myid" => int(1)
	"c" => string(1) "3"
	"ac" => int(1)
}

A->C->A

array(2) {
	"rid" => int(1)
	"a" => int(1)
}

string(25) "Undeclared property A->x."
 