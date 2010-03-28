<?php

/**
 * Test: isqua\Table\Helpers::isSelfReferencing()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Table;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';



class Test extends Table {
	static $PREFIX = 't';

	private $t_one;
	private $t_two;
	private $parent_id;

	static $PARENTS = array();
	static $CHILDREN = array('children'=>'Test');
}

class Test2 extends Table {
	static $PREFIX = 't2';

	private $parent_id;

	static $PARENTS = array();
	static $CHILDREN = array('children'=>'Test');
}

class Test3 extends Table {
	static $PREFIX = 't3';

	static $PARENTS = array();
	static $CHILDREN = array('children'=>'Test3');
}

Assert::true( Test::isSelfReferencing() );
Assert::false( Test2::isSelfReferencing() );
Assert::false( Test3::isSelfReferencing() );
