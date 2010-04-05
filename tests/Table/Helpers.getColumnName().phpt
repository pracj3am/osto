<?php

/**
 * Test: isqua\Table\Helpers::getColumnName()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;



require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 * @prefix a
 */
class Test extends Table {

	/**
	 * @null
	 * @column x_main
	 */	
	private $main;
	private $alt;
	private $a_alt;
	private $b;

	static $PARENTS = array();
	static $CHILDREN = array();
	static $NULL_COLUMNS = array();
}


Assert::same(Test::getColumnName('main'), 'x_main');
Assert::same(Test::getColumnName('alt'), 'a_alt');
Assert::same(Test::getColumnName('a_alt'), 'a_a_alt');
Assert::same(Test::getColumnName('a_a_alt'), 'a_a_alt');
Assert::same(Test::getColumnName('a_b'), 'a_b');
Assert::same(Test::getColumnName('b'), 'a_b');
Assert::false(Test::getColumnName('a_main'));
Assert::false(Test::getColumnName('a'));