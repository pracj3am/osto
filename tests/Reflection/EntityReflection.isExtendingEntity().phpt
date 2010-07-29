<?php

/**
 * Test: osto\Reflection\EntityReflection::isExtendingEntity()
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

Assert::false(Entity::getReflection()->isEntity());
Assert::true(A::getReflection()->isEntity());
Assert::false(B::getReflection()->isEntity());
Assert::true(C::getReflection()->isEntity());

Assert::false(A::getReflection()->isExtendingEntity());
Assert::false(B::getReflection()->isExtendingEntity());
Assert::false(C::getReflection()->isExtendingEntity());
Assert::true(D::getReflection()->isExtendingEntity());