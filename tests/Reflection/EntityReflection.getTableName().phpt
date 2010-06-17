<?php

/**
 * Test: isqua\Reflection\EntityReflection::getTableName()
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
 * @table fooo_bar
 */
class FooBar2 extends Entity {
}

/**
 * @table
 */
class FooBar3 extends Entity {
}

/** @table BarFoo */
class BarFoo extends Entity {
}

class aAaAaAaAAA extends Entity {
}

Assert::same( FooBar::getTableName(), 'foo_bar' );
Assert::same( FooBar2::getTableName(), 'fooo_bar' );
Assert::same( FooBar3::getTableName(), 'foo_bar3' );
Assert::same( BarFoo::getTableName(), 'BarFoo' );
Assert::same( aAaAaAaAAA::getTableName(), 'a_aa_aa_aa_a_a_a' );
