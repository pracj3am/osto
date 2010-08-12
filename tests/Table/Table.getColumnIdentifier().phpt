<?php

/**
 * Test: osto\Table::getColumnIdentifier()
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

db_connect();


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


Assert::same(Test::getTable()->getColumnIdentifier('main'), 'x_main');
Assert::same(Test::getTable()->getColumnIdentifier('alt'), 'a_alt');
Assert::same(Test::getTable()->getColumnIdentifier('a_alt'), 'a_a_alt');
Assert::same(Test::getTable()->getColumnIdentifier('a_a_alt'), 'a_a_alt');
Assert::same(Test::getTable()->getColumnIdentifier('a_b'), 'a_b');
Assert::same(Test::getTable()->getColumnIdentifier('b'), 'a_b');
Assert::same(Test::getTable()->getColumnIdentifier('b_id'), 'b_id');
Assert::false(Test::getTable()->getColumnIdentifier('a_main'));
Assert::false(B::getTable()->getColumnIdentifier('a_id'));
Assert::false(Test::getTable()->getColumnIdentifier('a'));