<?php

/**
 * Test: isqua\Reflection\EntityReflection global.
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
 * @prefix t
 * @property string $name, column="v"
 */
class TestA extends Entity {
	private $one;
	private $two;
	/** @column x_y */
	private $x_y;
	
	/** @has_many subTest */
	private $sub;

}

/** @prefix st */
class subTest extends Entity {
	private $one;
	private $two;
	/** @column x_y */
	private $x_y;

	/** @belongs_to TestA */
	private $test;	
}

Assert::same(TestA::getAnnotation('table'), 'model_test');
Assert::same(TestA::getAnnotation('prefix'), 't');
Assert::same(subTest::getAnnotation('prefix'), 'st');
Assert::same(subTest::getPropertyAnnotation('x_y', 'column'), 'x_y');
Assert::null(subTest::getPropertyAnnotation('x_y', 'pokutre'));
dump(TestA::getPropertyAnnotations('x_y'));

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

dump(TestA::getAnnotation('property'));

__halt_compiler();

------EXPECT------
array(1) {
	"column" => array(1) {
		0 => string(3) "x_y"
	}
} 

array(4) {
	"id" => string(4) "t_id"
	"one" => string(5) "t_one"
	"two" => string(5) "t_two"
	"x_y" => string(3) "x_y"
}

array(4) {
	"id" => string(4) "t_id"
	"one" => string(5) "t_one"
	"two" => string(5) "t_two"
	"x_y" => string(3) "x_y"
}

array(5) {
	"id" => string(5) "st_id"
	"one" => string(6) "st_one"
	"two" => string(6) "st_two"
	"x_y" => string(3) "x_y"
	"t_id" => string(4) "t_id"
}

object(isqua\Reflection\PropertyAnnotation) (5) {
	"type" => string(6) "string"
	"name" => string(4) "name"
	"column" => string(1) "v"
	"null" => bool(FALSE)
	"relation" => NULL
} 