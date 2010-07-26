<?php

/**
 * Test: osto\Reflection\EntityReflection::getPrefix()
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



class FooBar extends Entity {
}

/**
 * @prefix zd
 */
class FooBar2 extends Entity {
}

/**
 * @prefix
 */
class FooBar3 extends Entity {
}

/** @prefix bf */
class BarFoo extends Entity {
}

class aAaAaAaAAA extends Entity {
}

Assert::same( FooBar::getReflection()->getPrefix(), 'fb' );
Assert::same( FooBar2::getReflection()->getPrefix(), 'zd' );
Assert::same( FooBar3::getReflection()->getPrefix(), 'fb3' );
Assert::same( BarFoo::getReflection()->getPrefix(), 'bf' );
Assert::same( aAaAaAaAAA::getReflection()->getPrefix(), 'aaaaaa' );
