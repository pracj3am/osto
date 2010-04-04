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
	 * @column a_main
	 */	
	private $main;
	private $a_alt;
	private $a_a_alt;
	private $a_b;

	static $PARENTS = array();
	static $CHILDREN = array();
	static $NULL_COLUMNS = array();
}


Assert::same(Test::getColumnName('main'), 'main');
Assert::same(Test::getColumnName('alt'), 'a_alt');
Assert::same(Test::getColumnName('a_alt'), 'a_alt');
Assert::same(Test::getColumnName('a_a_alt'), 'a_a_alt');
Assert::same(Test::getColumnName('a_b'), 'a_b');
Assert::same(Test::getColumnName('b'), 'a_b');
Assert::false(Test::getColumnName('a_main'));
Assert::false(Test::getColumnName('a'));