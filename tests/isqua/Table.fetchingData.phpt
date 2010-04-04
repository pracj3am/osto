<?php

/**
 * Test: isqua\Table fetching data.
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

/**
 */
class Poiu extends Table {

	private $p_a;
	private $p_p_koo;
	private $p_koo;
	private $zzz;

	static $PARENTS = array();
	static $CHILDREN = array();
	static $NULL_COLUMNS = array('zzz');
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
$p->save();

foreach (Poiu::getAll() as $row)
	dump($row->values);

__halt_compiler();

------EXPECT------
array(5) {
	"a" => string(1) "3"
	"p_p_koo" => string(122) "sdk agjdfj gkjdfgkjdfjg sdůjf új ai gjaúg jaúsgfo ajdůjůajg ůadj gůjdfgůljfadojúgjůvj ůdfk ůkdfajg kůdjfgsdg"
	"koo" => string(3) "wer"
	"zzz" => string(19) "2009-01-01 12:00:01"
	"id" => int(1)
}