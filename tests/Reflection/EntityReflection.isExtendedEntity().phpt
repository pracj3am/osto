<?php

/**
 * Test: isqua\Reflection\EntityReflection::isExtendedEntity()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';

define('ISQUA_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(ISQUA_TMP_DIR);


/**
 * @property string $n, null
 */
class A extends Entity 
{

}

abstract class B extends A
{
	
}

class C extends B 
{
	
}

class D extends A 
{
	
}

Assert::false(Entity::isEntity());
Assert::true(A::isEntity());
Assert::false(B::isEntity());
Assert::true(C::isEntity());

Assert::false(A::isExtendedEntity());
Assert::false(B::isExtendedEntity());
Assert::false(C::isExtendedEntity());
Assert::true(D::isExtendedEntity());