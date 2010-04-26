<?php

/**
 * Test: isqua\Table\Helpers::isNullColumn
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;



require __DIR__ . '/../NetteTest/initialize.php';



class A extends Table {
	/** @null */
	private $n;
	private $m;
	
	/** @belongs_to B @null */
	private $ab;
	/** @belongs_to C */
	private $ac;
}

class B extends Table {
	
}

class C extends Table {
	
}

Assert::true(A::isNullColumn('n'));
Assert::false(A::isNullColumn('m'));
Assert::false(A::isNullColumn('foo'));
Assert::true(A::isNullColumn('b_id'));
Assert::false(A::isNullColumn('c_id'));
