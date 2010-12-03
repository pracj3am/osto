<?php

/**
 * Test: osto\Entity::has...().
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
	CREATE TEMPORARY TABLE `test`.`poiu` (
	`p_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_a` INT NOT NULL
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`lkjh` (
	`l_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_id` INT NOT NULL ,
	`l_foo` VARCHAR(2048),
	KEY (p_id) 
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`mnbv` (
	`m_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_id` INT NULL ,
	`m_boo` VARCHAR(2048) NOT NULL,
	KEY (p_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

/**
 * @property Lkjh $L , has_many
 * @property Mnbv $M , has_one
 */
class Poiu extends Entity 
{

}

/**
 * @property string $foo
 * @property Poiu $p , belongs_to
 */
class Lkjh extends Entity 
{

}

/**
 * @property string $boo
 * @property Poiu $p , belongs_to, null
 */
class Mnbv extends Entity 
{

}

$p = new Poiu;

$p->M = new Mnbv;
$p->M->boo = 'a';

$l1 = new Lkjh;
$l1->foo = 'b';
$l2 = new Lkjh;
$l2->foo = 'c';

$p->L[] = $l1;
$p->L[] = $l2;

$p->save();
$p_id = $p->id;
unset($p);

$m = new Mnbv;
$m->boo = 'last';
$m->save();
$m_id = $m->id;
unset($m);

$p = Poiu($p_id);
$m = Mnbv($m_id);

$p2 = new Poiu;
$p2->save();

$p3 = new Poiu;

Assert::true($p->hasL());
Assert::true($p->hasL('[:foo:] = %s', 'b'));
Assert::false($p->hasL('[:foo:] = %s', 'd'));
Assert::true($p->hasM());
Assert::true($p->hasM('[:boo:] = %s', 'a'));
Assert::false($p->hasM('[:boo:] = %s', 'x'));
Assert::true($p->L->fetch()->hasP());
Assert::true($p->M->hasP());
Assert::false($m->hasP());
Assert::false($p2->hasL());
Assert::false($p2->hasM());
Assert::false($p3->hasL());
Assert::false($p3->hasM());
