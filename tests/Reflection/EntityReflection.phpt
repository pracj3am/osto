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

define('ISQUA_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(ISQUA_TMP_DIR);



/**
 * @table model_test
 * @prefix t
 * @property string $one
 * @property string $two
 * @property string $x_y, column=x_y
 * @property subTest $sub, has_many
 */
class TestA extends Entity 
{

}

/** 
 * @prefix st
 * @property string $one
 * @property string $two
 * @property string $x_y, column=x_y
 * @property TestA $test, belongs_to
 */
class subTest extends Entity 
{

}

Assert::same(TestA::getAnnotation('table'), 'model_test');
Assert::same(TestA::getAnnotation('prefix'), 't');
Assert::same(subTest::getAnnotation('prefix'), 'st');

dump(TestA::getAnnotations('property'));

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

__halt_compiler();

------EXPECT------
array(4) {
	0 => object(isqua\Reflection\PropertyAnnotation) (5) {
		"type" => string(6) "string"
		"name" => string(3) "one"
		"column" => string(5) "t_one"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
	}
	1 => object(isqua\Reflection\PropertyAnnotation) (5) {
		"type" => string(6) "string"
		"name" => string(3) "two"
		"column" => string(5) "t_two"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
	}
	2 => object(isqua\Reflection\PropertyAnnotation) (5) {
		"type" => string(6) "string"
		"name" => string(3) "x_y"
		"column" => string(3) "x_y"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
	}
	3 => object(isqua\Reflection\PropertyAnnotation) (5) {
		"type" => string(7) "subTest"
		"name" => string(3) "sub"
		"column" => NULL
		"null" => bool(FALSE)
		"relation" => string(8) "has_many"
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