<?php

/**
 * Test: isqua\Table\Helpers::getTableName()
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
 * @table fooo_bar
 */
class FooBar2 extends Table {
}

/**
 * @table
 */
class FooBar3 extends Table {
}

/** @table BarFoo */
class BarFoo extends Table {
}

class aAaAaAaAAA extends Table {
}

Assert::same( FooBar::getTableName(), 'foo_bar' );
Assert::same( FooBar2::getTableName(), 'fooo_bar' );
Assert::same( FooBar3::getTableName(), 'foo_bar3' );
Assert::same( BarFoo::getTableName(), 'BarFoo' );
Assert::same( aAaAaAaAAA::getTableName(), 'a_aa_aa_aa_a_a_a' );
