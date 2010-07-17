<?php

/**
 * Test: osto\Reflection\EntityReflection::isExtendedEntity()
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