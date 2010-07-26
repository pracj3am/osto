<?php

/**
 * Test: osto\Reflection\EntityReflection::getColumnName()
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
 * @prefix a
 * @property string $main, column=x_main, null
 * @property string $alt
 * @property string $a_alt
 * @property string $b
 * @property B $abs, belongs_to
 */
class Test extends Entity 
{
}

/**
 * @property Test $xyz, has_many
 */
class B extends Entity 
{
}


Assert::same(Test::getReflection()->getColumnName('main'), 'x_main');
Assert::same(Test::getReflection()->getColumnName('alt'), 'a_alt');
Assert::same(Test::getReflection()->getColumnName('a_alt'), 'a_a_alt');
Assert::same(Test::getReflection()->getColumnName('a_a_alt'), 'a_a_alt');
Assert::same(Test::getReflection()->getColumnName('a_b'), 'a_b');
Assert::same(Test::getReflection()->getColumnName('b'), 'a_b');
Assert::same(Test::getReflection()->getColumnName('b_id'), 'b_id');
Assert::false(Test::getReflection()->getColumnName('a_main'));
Assert::false(B::getReflection()->getColumnName('a_id'));
Assert::false(Test::getReflection()->getColumnName('a'));