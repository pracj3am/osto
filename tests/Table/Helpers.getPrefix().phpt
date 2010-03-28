<?php

/**
 * Test: isqua\Table\Helpers::getPrefix()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';



class FooBar extends Table {
}

/**
 * @prefix zd
 */
class FooBar2 extends Table {
}

/**
 * @prefix
 */
class FooBar3 extends Table {
}

/** @prefix bf */
class BarFoo extends Table {
}

class aAaAaAaAAA extends Table {
}

Assert::same( FooBar::getPrefix(), 'fb' );
Assert::same( FooBar2::getPrefix(), 'zd' );
Assert::same( FooBar3::getPrefix(), 'fb3' );
Assert::same( BarFoo::getPrefix(), 'bf' );
Assert::same( aAaAaAaAAA::getPrefix(), 'aaaaaa' );
