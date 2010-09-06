<?php

/**
 * Test: osto\Reflection\EntityReflection::isSelfReferencing()
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto\Table
 * @subpackage UnitTests
 */

use osto\Entity;
use osto\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);



/**
 * @prefix t
 * @property int $one
 * @property int $two
 * @property Test $Parent , belongs_to, column=parent_id
 * @property Test $children, has_many 
 */
class Test extends Entity 
{

}

/**
 * @prefix t2
 * @property Test2 $Parent , belongs_to, column=parent_id
 * @property Test $children, has_many 
 */
class Test2 extends Entity 
{

}

/** 
 * @prefix t3
 * @property Test3 $Parent , belongs_to, column=xxx_id
 * @property Test3 $children, has_many 
 */
class Test3 extends Entity 
{

}

/** 
 * @prefix t4
 * @property Test4 $Parent , belongs_one, column=parent_id
 * @property Test4 $children, has_many 
 */
class Test4 extends Entity 
{

}

Assert::true( Test::getReflection()->isSelfReferencing() );
Assert::false( Test2::getReflection()->isSelfReferencing() );
Assert::true( Test3::getReflection()->isSelfReferencing() );
Assert::false( Test4::getReflection()->isSelfReferencing() );