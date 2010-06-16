<?php

/**
 * Test: isqua\Table\Reflection::getPrefix()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';



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

Assert::same( FooBar::getPrefix(), 'fb' );
Assert::same( FooBar2::getPrefix(), 'zd' );
Assert::same( FooBar3::getPrefix(), 'fb3' );
Assert::same( BarFoo::getPrefix(), 'bf' );
Assert::same( aAaAaAaAAA::getPrefix(), 'aaaaaa' );
