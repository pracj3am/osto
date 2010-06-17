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