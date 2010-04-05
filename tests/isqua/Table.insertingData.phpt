<?php

/**
 * Test: isqua\Table inserting data.
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
 * @subpackage UnitTests
 */

use isqua\Table;



require __DIR__ . '/../NetteTest/initialize.php';


db_connect();


dibi::query('
	CREATE TEMPORARY TABLE `test`.`poiu` (
	`p_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_a` INT NOT NULL ,
	`p_p_koo` VARCHAR( 255 ) NOT NULL ,
	`p_koo` VARCHAR( 255 ) NOT NULL ,
	`zzz` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
	) ENGINE = InnoDB;
');


dibi::query('
	CREATE TEMPORARY TABLE `test`.`lkjh` (
	`l_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`p_id` INT NOT NULL ,
	`l_content` TEXT,
	KEY (p_id) /*,
	FOREIGN KEY (p_id) REFERENCES `poiu` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE*/
	) ENGINE = InnoDB;
');


/**
 */
class Poiu extends Table {

	private $a;
	private $p_koo;
	private $koo;
	/** @column zzz */
	private $zzz;

	static $PARENTS = array();
	static $CHILDREN = array('l'=>'Lkjh');
	static $NULL_COLUMNS = array('zzz');
}

class Lkjh extends Table {

	/** @column p_id */
	private $p_id;
	private $content;

	static $PARENTS = array('p'=> 'Poiu');
	static $CHILDREN = array();
	static $NULL_COLUMNS = array();
}


$p = new Poiu;

$p->a = 1;
$p->p_koo = 2;
$p->koo = 'jut';
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
$l2->content = 'bla2 bla2';

$p->l[] = $l1;
$p->l[] = $l2;

$p->save();

unset($p);

foreach (Poiu::getAll() as $row)
	dump($row->values);

$p = Poiu::getOne(array('a'=>3));

foreach ($p->l as $row)
	dump($row->values);

dump($p->values);

$ls = Lkjh::getAll();

$l = $ls->getFirst();
$l->loadP();
dump($l->values);

__halt_compiler();

------EXPECT------
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

array(3) {
	"id" => int(1)
	"p_id" => string(1) "2"
	"content" => string(9) "bla1 bla1"
}

array(3) {
	"id" => int(2)
	"p_id" => string(1) "2"
	"content" => string(9) "bla2 bla2"
}

array(6) {
	"id" => int(2)
	"a" => string(1) "3"
	"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => string(19) "2009-01-01 12:00:01"
	"l" => array(2) {
		1 => array(3) {
			"id" => int(1)
			"p_id" => string(1) "2"
			"content" => string(9) "bla1 bla1"
		}
		2 => array(3) {
			"id" => int(2)
			"p_id" => string(1) "2"
			"content" => string(9) "bla2 bla2"
		}
	}
}

array(4) {
	"id" => int(1)
	"p_id" => string(1) "2"
	"content" => string(9) "bla1 bla1"
	"p" => array(5) {
		"id" => int(2)
		"a" => string(1) "3"
		"p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
		"koo" => string(3) "wer"
		"zzz" => string(19) "2009-01-01 12:00:01"
	}
} 