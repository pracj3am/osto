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

try {
    Assert::false(Entity::getReflection()->isEntity());
} catch (Exception $e) {
    dump($e->getMessage());
}
try {
    Assert::false(B::getReflection()->isEntity());
} catch (Exception $e) {
    dump($e->getMessage());
}
Assert::true(C::getReflection()->isEntity());
Assert::true(A::getReflection()->isEntity());

Assert::false(A::getReflection()->isExtendingEntity());
Assert::false(C::getReflection()->isExtendingEntity());
Assert::true(D::getReflection()->isExtendingEntity());

__halt_compiler();
------EXPECT------
string(57) "Cannot create reflection: 'osto\Entity' is not an entity."

string(47) "Cannot create reflection: 'B' is not an entity."
