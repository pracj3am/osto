<?php

/**
 * Test: isqua\Reflection\EntityReflection::isSelfReferencing()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';

define('ISQUA_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(ISQUA_TMP_DIR);



/**
 * @prefix t
 * @property int $one
 * @property int $two
 * @property int $parent_id, column=parent_id
 * @property Test $children, has_many 
 */
class Test extends Entity 
{

}

/**
 * @prefix t2
 * @property int $parent_id
 * @property Test $children, has_many 
 */
class Test2 extends Entity 
{

}

/** 
 * @prefix t3
 * @property int $xxxent_id, column=parent_id
 * @property Test3 $children, has_many 
 */
class Test3 extends Entity 
{

}

/** 
 * @prefix t4
 * @property int $xxxent_id, column=xxxent_id
 * @property Test4 $children, has_many 
 */
class Test4 extends Entity 
{

}

Assert::true( Test::isSelfReferencing() );
Assert::false( Test2::isSelfReferencing() );
Assert::true( Test3::isSelfReferencing() );
Assert::false( Test4::isSelfReferencing() );