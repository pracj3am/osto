<?php

/**
 * Test: isqua\Reflection\EntityReflection::getColumnName()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 * @prefix a
 */
class Test extends Entity {

	/**
	 * @null
	 * @column x_main
	 */	
	private $main;
	private $alt;
	private $a_alt;
	private $b;

	/** @belongs_to B */
	private $abc;
}

class B extends Entity {
	/** @has_many Test */
	private $xyz;
	
}


Assert::same(Test::getColumnName('main'), 'x_main');
Assert::same(Test::getColumnName('alt'), 'a_alt');
Assert::same(Test::getColumnName('a_alt'), 'a_a_alt');
Assert::same(Test::getColumnName('a_a_alt'), 'a_a_alt');
Assert::same(Test::getColumnName('a_b'), 'a_b');
Assert::same(Test::getColumnName('b'), 'a_b');
Assert::same(Test::getColumnName('b_id'), 'b_id');
Assert::false(Test::getColumnName('a_main'));
Assert::false(B::getColumnName('a_id'));
Assert::false(Test::getColumnName('a'));