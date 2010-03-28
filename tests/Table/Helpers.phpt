<?php

/**
 * Test: isqua\Table\Helpers global.
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 */
class TestA extends Table {
	static $PREFIX = 't';

	private $t_one;
	private $t_two;
	private $x_y;

	static $PARENTS = array();
	static $CHILDREN = array('sub'=>'subTest');
}

class subTest extends Table {
	static $PREFIX = 'st';

	private $st_one;
	private $st_two;
	private $x_y;

	static $PARENTS = array('test'=>'TestA');
	static $CHILDREN = array();
}

Assert::same( TestA::getColumnName('one'),  't_one' );
Assert::same( TestA::getColumnName('one'),  't_one' );

Assert::same( subTest::getColumnName('test.one'),  'test.t_one' );
Assert::same( subTest::getColumnName('test.one', 'xxx'),  'xxx->test.t_one' );
Assert::same( subTest::getColumnName('test.x_y', 'xxx'),  'xxx->test.x_y' );

Assert::same( TestA::getTableName(), 'model_test' );
Assert::same( subTest::getTableName(), 'sub_test' );

Assert::false( TestA::isColumn('one') );
Assert::false( TestA::isColumn('one') );
Assert::true( TestA::isColumn('t_one') );
Assert::false( TestA::isColumn('st_one') );
Assert::true( subTest::isColumn('st_one') );
Assert::true( subTest::isColumn('x_y') );
Assert::true( TestA::isColumn('x_y') );

dump(TestA::getColumns());

dump(TestA::getColumns());

dump(subTest::getColumns());

dump(subTest::getAllColumns());



__halt_compiler();

------EXPECT------
array(4) {
	0 => string(4) "t_id"
	1 => string(5) "t_one"
	2 => string(5) "t_two"
	3 => string(3) "x_y"
}

array(4) {
	0 => string(4) "t_id"
	1 => string(5) "t_one"
	2 => string(5) "t_two"
	3 => string(3) "x_y"
}

array(4) {
	0 => string(5) "st_id"
	1 => string(6) "st_one"
	2 => string(6) "st_two"
	3 => string(3) "x_y"
}

array(7) {
	0 => string(5) "st_id"
	1 => string(6) "st_one"
	2 => string(6) "st_two"
	3 => string(3) "x_y"
	4 => string(4) "t_id"
	5 => string(5) "t_one"
	6 => string(5) "t_two"
}