<?php

/**
 * Test: osto\Entity inserting data.
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
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_a` INT NOT NULL ,
	`p_p_koo` VARCHAR( 255 ) NOT NULL ,
	`p_koo` VARCHAR( 255 ) NOT NULL ,
	`zzz` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`lkjh` (
	`l_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_id` INT NOT NULL ,
	`l_content` VARCHAR(2048) DEFAULT "",
	KEY (p_id) /*,
	FOREIGN KEY (p_id) REFERENCES `poiu` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE*/
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

dibi::query('
	CREATE TEMPORARY TABLE `test`.`mnbv` (
	`m_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`id` INT NULL ,
	`m_boo` VARCHAR(2048) NOT NULL DEFAULT "-",
	KEY (id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8;
');

/**
 * @property int $id, column=id, primary_key
 * @property int $a
 * @property string $p_koo
 * @property string $koo
 * @property Datetime $zzz, column=zzz, null
 * @property Lkjh $L, has_many, column=p_id
 * @property Mnbv $M, has_one
 */
class Poiu extends Entity 
{

}

/**
 * @property string $content, null
 * @property Poiu $p, belongs_to, column=p_id
 */
class Lkjh extends Entity 
{

}

/**
 * @property int $myid, primary_key
 * @property string $boo
 * @property Poiu $p, belongs_to
 */
class Mnbv extends Entity 
{

}

$p = new Poiu;

$p->a = 1;
$p->p_koo = 2;
$p->koo = 'jut';

$p->M = new Mnbv;

$p->save();

unset($p);
$p = new Poiu;

$p->p_a = 3;
$p->p_p_koo = 'sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg';
$p->koo = 'wer';
$p->zzz = '2009-01-01 12:00:01';

$l1 = new Lkjh;
$l1->content = 'bla1 bla1';
$l2 = new Lkjh;

$p->L[] = $l1;
$p->L[] = $l2;

$p->M = new Mnbv;
$p->M->boo = 'Howgh';

$p->save();

unset($p);

output('All P\'s:');
foreach (Poiu::getAll() as $row)
	dump($row->values);

$p = Poiu::getOne(array('a'=>3));

output('Children of P with a=3:');
foreach ($p->L as $row)
	dump($row->values);

output('... and the P itself');
dump($p->values);

output('Parent of the first L:');
$ls = Lkjh::getAll();

$l = $ls->getFirst();
$l->loadP();
dump($l->values);

output('All M\'s:');
foreach (Mnbv::getAll() as $row)
	dump($row->values);

__halt_compiler();

------EXPECT------
All P%c%s:

array(5) {
	"id" => int(1)
	"a" => string(1) "1"
	"p_koo" => string(1) "2"
	"koo" => string(3) "jut"
	"zzz" => string(19) "%d%-%d%-%d% %d%:%d%:%d%"
}

array(5) {
	"id" => int(2)
	"a" => string(1) "3"
	"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => string(19) "2009-01-01 12:00:01"
}

Children of P with a=3:

array(3) {
	"id" => int(1)
	"content" => string(9) "bla1 bla1"
	"p_id" => string(1) "2"
}

array(3) {
	"id" => int(2)
	"content" => NULL
	"p_id" => string(1) "2"
}

... and the P itself

array(6) {
	"id" => int(2)
	"a" => string(1) "3"
	"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => string(19) "2009-01-01 12:00:01"
	"L" => array(2) {
		1 => array(3) {
			"id" => int(1)
			"content" => string(9) "bla1 bla1"
			"p_id" => string(1) "2"
		}
		2 => array(3) {
			"id" => int(2)
			"content" => NULL
			"p_id" => string(1) "2"
		}
	}
}

Parent of the first L:

array(4) {
	"id" => int(1)
	"content" => string(9) "bla1 bla1"
	"p_id" => string(1) "2"
	"p" => array(5) {
		"id" => int(2)
		"a" => string(1) "3"
		"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
		"koo" => string(3) "wer"
		"zzz" => string(19) "2009-01-01 12:00:01"
	}
} 

All M%c%s:

array(3) {
	"myid" => int(1)
	"boo" => string(1) "-"
	"id" => string(1) "1"
}

array(3) {
	"myid" => int(2)
	"boo" => string(5) "Howgh"
	"id" => string(1) "2"
}