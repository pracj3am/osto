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

Assert::true(A::getReflection()->isNullColumn('n'));
Assert::true(A::getReflection()->isNullColumn('k'));
Assert::false(A::getReflection()->isNullColumn('l'));
Assert::false(A::getReflection()->isNullColumn('m'));
Assert::false(A::getReflection()->isNullColumn('foo'));
Assert::true(A::getReflection()->isNullColumn('b_id'));
Assert::false(A::getReflection()->isNullColumn('c_id'));
