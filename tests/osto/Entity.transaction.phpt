<?php

/**
 * Test: osto\Entity transactions.
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

use osto\Entity;
use osto\DatabaseException;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);


db_connect();


dibi::query('
	CREATE TEMPORARY TABLE `test`.`poiu` (
	`p_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_a` INT NOT NULL ,
	`p_p_koo` VARCHAR( 255 ) NOT NULL ,
	`p_koo` VARCHAR( 255 ) NOT NULL 
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`mnbv` (
	`m_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_id` INT NULL ,
	`m_boo` INT NOT NULL ,
	KEY (p_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

/**
 * @property int $a , null
 * @property string $p_koo
 * @property string $koo
 * @property Mnbv $M , has_one
 */
class Poiu extends Entity 
{

    
}


/**
 * @property string $boo , null
 * @property Poiu $p , belongs_to
 */
class Mnbv extends Entity 
{

}

$p = new Poiu;

$p->a = 1;
$p->p_koo = 2;
$p->koo = 'jut';

$m = new Mnbv;
$m->p = $p;

try {
    $m->save();
    Assert::true(FALSE); //fail
} catch (DatabaseException $e) {
    
}


$p = new Poiu;

//$p->a = 1;
$p->p_koo = 2;
$p->koo = 'jut';

$m = new Mnbv;
$m->boo = 8;
$m->p = $p;

try {
    $m->save();
    Assert::true(FALSE); //fail
} catch (DatabaseException $e) {
    
}

$m->p->a = 1;
Entity::begin();
$m->save();
Entity::rollback();

Entity::begin();
$m->save();
Entity::commit();

Assert::same(Poiu::count(), 1);
Assert::same(Mnbv::count(), 1);
