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


/** @prefix t */
class Test extends Table {

	private $one;
	private $two;
	/** @column parent_id */
	private $parent_id;

	/** @has_many Test */
	private $children;
}

/** @prefix t2 */
class Test2 extends Table {

	private $parent_id;

	/** @has_many Test */
	private $children;
}

/** @prefix t3 */
class Test3 extends Table {

	/** @column rrrent_id */
	private $parent_id;

	/** @has_many Test3 */
	private $children;
}

Assert::true( Test::isSelfReferencing() );
Assert::false( Test2::isSelfReferencing() );
Assert::false( Test3::isSelfReferencing() );
