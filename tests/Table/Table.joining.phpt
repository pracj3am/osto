<?php

/**
 * Test: osto\Table joining
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



/**
 * @property int $aid , column=sid, primary_key
 * @property int $a
 * @property C $C , belongs_to
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

/**
 * @property string $c
 * @property A $A , has_many
 */
class C extends Entity
{

}


/**
 * @property string $d
 * @property B $B , belongs_to
 */
class D extends Entity
{

}

$a1 = new Table('A');
$a2 = new Table('A');
$b1 = new Table('B');
$b2 = new Table('B');
$c = new Table('C');
$d = new Table('D');

output($a1->join($c));
output($a2->select(':C.c:'));
output($b1->select(':a:'));
output($b2->select(':C.c:'));
output($d->select(':B.a:'));



__halt_compiler();
------EXPECT------

			SELECT *
			FROM `a` AS `$a` JOIN (`c` AS `$a->C`) USING (`c_id`)





			SELECT `$a->C`.`c_c`
			FROM `a` AS `$a` JOIN (`c` AS `$a->C`) USING (`c_id`)





			SELECT `$b->extendedEntity`.`a_a`
			FROM `b` AS `$b` JOIN (`a` AS `$b->extendedEntity`) USING (`sid`)





			SELECT `$b->extendedEntity->C`.`c_c`
			FROM `b` AS `$b` JOIN (`a` AS `$b->extendedEntity` JOIN (`c` AS `$b->extendedEntity->C`) USING (`c_id`)) USING (`sid`)





			SELECT `$d->B->extendedEntity`.`a_a`
			FROM `d` AS `$d` JOIN (`b` AS `$d->B` JOIN (`a` AS `$d->B->extendedEntity`) USING (`sid`)) USING (`b_id`)



                    