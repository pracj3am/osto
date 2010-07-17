<?php

/**
 * Test: osto\Reflection\EntityReflection::isNullColumn
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto\Table
 * @subpackage UnitTests
 */

use osto\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);


/**
 * @property string $n, null
 * @property string $k, null=true
 * @property string $l, null=false
 * @property string $m
 * @property B $ab, belongs_to, null
 * @property C $ac, belongs_to
 */
class A extends Entity 
{

}

class B extends Entity 
{
	
}

class C extends Entity 
{
	
}

Assert::true(A::isNullColumn('n'));
Assert::true(A::isNullColumn('k'));
Assert::false(A::isNullColumn('l'));
Assert::false(A::isNullColumn('m'));
Assert::false(A::isNullColumn('foo'));
Assert::true(A::isNullColumn('b_id'));
Assert::false(A::isNullColumn('c_id'));
