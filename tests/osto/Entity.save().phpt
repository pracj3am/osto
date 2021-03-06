<?php

/**
 * Test: osto\Entity::save().
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
 * @property Datetime $zzz , column=zzz, null
 * @property Lkjh $L , has_many, column=p_id
 * @property Mnbv $M , has_one
 */
class Poiu extends Entity 
{
    protected function beforeSave(&$values, &$values_update) {
        $values['p_a']++;
    }
    protected function afterSave(&$values, &$values_update) {
        output('Saved sucessfully');
    }
}

/**
 * @property string $content, null
 * @property Poiu $p , belongs_to, column=p_id
 */
class Lkjh extends Entity 
{

}

/**
 * @property int $myid , primary_key
 * @property string $boo
 * @property Poiu $p , belongs_to
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

//change value and save
$p->koo = 'lut';
$p->save();

unset($p);
$p = new Poiu;

$p['p_a'] = 3;
$p->p_koo = 'sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg';
$p->koo = 'wer';
$p->zzz = '2009-01-01 12:00:01.000000';

$l1 = new Lkjh;
$l1->content = 'bla1 bla1';
$l2 = new Lkjh;
$l3 = new Lkjh;

$p->L[] = $l1;
$p->L[] = $l2;
$p->L[] = $l3;

$p->M = new Mnbv;
$p->M->boo = 'Howgh';

$p->save();

//set value and save
$l3->content = 'mlok';
$l3->save();

unset($p);

$m = new Mnbv();
$m->boo = 'last';
$m->p->a = 8;
$m->save();

unset($m);

output('All P\'s:');
foreach (Poiu::findAll() as $row)
	dump($row->values);

$p = Poiu::findOne('p_a=',4);

output('Children of P with a=4:');
foreach ($p->L as $row)
	dump($row->values);

output('... and the P itself');
dump($p->values);

//test přidání dalšího L pomocí []
$p->L[] = $l1->copy();
$p->save();
Assert::same(Lkjh::count(), 4);

//uložení po vynulování relací
$p->L = NULL;
$p->M = NULL;
try {
    $p->save();
} catch (\Exception $e) {
    output('Never should be here');
}

output('Parent of the first L:');
$ls = Lkjh::findAll();

$l = $ls->fetch();
$l->loadP();
dump($l->values);

output('All M\'s:');
foreach (Mnbv::findAll() as $row)
	dump($row->values);

__halt_compiler();

------EXPECT------
Saved sucessfully

Saved sucessfully

Saved sucessfully

Saved sucessfully

All P%c%s:

array(5) {
	"id" => int(1)
	"a" => int(2)
	"p_koo" => string(1) "2"
	"koo" => string(3) "lut"
	"zzz" => object(DateTime) (3) {
		"date" => string(26) "%d%-%d%-%d% %d%:%d%:%d%.000000"
		"timezone_type" => int(3)
		"timezone" => string(13) "Europe/Prague"
	}
}

array(5) {
	"id" => int(2)
	"a" => int(4)
	"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => object(DateTime) (3) {
		"date" => string(26) "2009-01-01 12:00:01.000000"
		"timezone_type" => int(3)
		"timezone" => string(13) "Europe/Prague"
	}
}

array(5) {
	"id" => int(3)
	"a" => int(9)
	"p_koo" => string(0) ""
	"koo" => string(0) ""
	"zzz" => object(DateTime) (3) {
		"date" => string(26) "%d%-%d%-%d% %d%:%d%:%d%.000000"
		"timezone_type" => int(3)
		"timezone" => string(13) "Europe/Prague"
	}
}

Children of P with a=4:

array(3) {
	"id" => int(1)
	"content" => string(9) "bla1 bla1"
	"p_id" => int(2)
}

array(3) {
	"id" => int(2)
	"content" => NULL
	"p_id" => int(2)
}

array(3) {
	"id" => int(3)
	"content" => string(4) "mlok"
	"p_id" => int(2)
}

... and the P itself

array(6) {
	"id" => int(2)
	"a" => int(4)
	"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => object(DateTime) (3) {
		"date" => string(26) "2009-01-01 12:00:01.000000"
		"timezone_type" => int(3)
		"timezone" => string(13) "Europe/Prague"
	}
	"L" => array(3) {
		0 => array(3) {
			"id" => int(1)
			"content" => string(9) "bla1 bla1"
			"p_id" => int(2)
		}
		1 => array(3) {
			"id" => int(2)
			"content" => NULL
			"p_id" => int(2)
		}
		2 => array(3) {
			"id" => int(3)
			"content" => string(4) "mlok"
			"p_id" => int(2)
		}
	}
}

Parent of the first L:

array(4) {
	"id" => int(1)
	"content" => string(9) "bla1 bla1"
	"p_id" => int(2)
	"p" => array(5) {
		"id" => int(2)
		"a" => int(4)
		"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
		"koo" => string(3) "wer"
		"zzz" => object(DateTime) (3) {
			"date" => string(26) "2009-01-01 12:00:01.000000"
			"timezone_type" => int(3)
			"timezone" => string(13) "Europe/Prague"
		}
	}
}

All M%c%s:

array(3) {
	"myid" => int(1)
	"boo" => string(1) "-"
	"id" => int(1)
}

array(3) {
	"myid" => int(2)
	"boo" => string(5) "Howgh"
	"id" => int(2)
}

array(3) {
	"myid" => int(3)
	"boo" => string(4) "last"
	"id" => int(3)
}
