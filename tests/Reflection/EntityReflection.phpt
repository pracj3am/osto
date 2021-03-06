<?php

/**
 * Test: osto\Reflection\EntityReflection global.
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto\Table
 * @subpackage UnitTests
 */

use osto\Entity;




require __DIR__ . '/../NetteTest/initialize.php';

define('OSTO_TMP_DIR', __DIR__ . '/tmp');
NetteTestHelpers::purge(OSTO_TMP_DIR);



/**
 * @table model_test
 * @prefix t
 * @property int $myid, primary_key, column=mymyid
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

Assert::same(TestA::getReflection()->getAnnotation('table'), 'model_test');
Assert::same(TestA::getReflection()->getAnnotation('prefix'), 't');
Assert::same(subTest::getReflection()->getAnnotation('prefix'), 'st');

dump(TestA::getReflection()->getAnnotations('property'));

Assert::same( TestA::getReflection()->getTableName(), 'model_test' );
Assert::same( subTest::getReflection()->getTableName(), 'sub_test' );

Assert::false( TestA::getReflection()->isColumn('one') );
Assert::false( TestA::getReflection()->isColumn('one') );
Assert::true( TestA::getReflection()->isColumn('t_one') );
Assert::false( TestA::getReflection()->isColumn('st_one') );
Assert::true( subTest::getReflection()->isColumn('st_one') );
Assert::true( subTest::getReflection()->isColumn('x_y') );
Assert::true( TestA::getReflection()->isColumn('x_y') );

dump(TestA::getReflection()->getColumns());

dump(TestA::getReflection()->getColumns());

dump(subTest::getReflection()->getColumns());

Assert::same( 'TestA', TestA::getReflection()->getShortName() );


__halt_compiler();

------EXPECT------
array(5) {
	0 => object(osto\Reflection\PropertyAnnotation) (6) {
		"type" => string(3) "int"
		"name" => string(4) "myid"
		"column" => string(6) "mymyid"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
		"primary_key" => bool(TRUE)
	}
	1 => object(osto\Reflection\PropertyAnnotation) (6) {
		"type" => string(6) "string"
		"name" => string(3) "one"
		"column" => string(5) "t_one"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
		"primary_key" => bool(FALSE)
	}
	2 => object(osto\Reflection\PropertyAnnotation) (6) {
		"type" => string(6) "string"
		"name" => string(3) "two"
		"column" => string(5) "t_two"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
		"primary_key" => bool(FALSE)
	}
	3 => object(osto\Reflection\PropertyAnnotation) (6) {
		"type" => string(6) "string"
		"name" => string(3) "x_y"
		"column" => string(3) "x_y"
		"null" => bool(FALSE)
		"relation" => bool(FALSE)
		"primary_key" => bool(FALSE)
	}
	4 => object(osto\Reflection\PropertyAnnotation) (6) {
		"type" => string(7) "subTest"
		"name" => string(3) "sub"
		"column" => NULL
		"null" => bool(FALSE)
		"relation" => string(8) "has_many"
		"primary_key" => bool(FALSE)
	}
}

array(4) {
	"myid" => string(6) "mymyid"
	"one" => string(5) "t_one"
	"two" => string(5) "t_two"
	"x_y" => string(3) "x_y"
}

array(4) {
	"myid" => string(6) "mymyid"
	"one" => string(5) "t_one"
	"two" => string(5) "t_two"
	"x_y" => string(3) "x_y"
}

array(5) {
	"id" => string(5) "st_id"
	"one" => string(6) "st_one"
	"two" => string(6) "st_two"
	"x_y" => string(3) "x_y"
	"test" => string(6) "mymyid"
}