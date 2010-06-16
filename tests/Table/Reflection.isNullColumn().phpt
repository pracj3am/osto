<?php

/**
 * Test: isqua\Table\Reflection::isNullColumn
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;



require __DIR__ . '/../NetteTest/initialize.php';



class A extends Entity {
	/** @null */
	private $n;
	private $m;
	
	/** @belongs_to B @null */
	private $ab;
	/** @belongs_to C */
	private $ac;
}

class B extends Entity {
	
}

class C extends Entity {
	
}

Assert::true(A::isNullColumn('n'));
Assert::false(A::isNullColumn('m'));
Assert::false(A::isNullColumn('foo'));
Assert::true(A::isNullColumn('b_id'));
Assert::false(A::isNullColumn('c_id'));
