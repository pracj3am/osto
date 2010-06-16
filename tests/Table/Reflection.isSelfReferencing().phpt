<?php

/**
 * Test: isqua\Table\Reflection::isSelfReferencing()
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;
use isqua\Table\Helpers;




require __DIR__ . '/../NetteTest/initialize.php';


/** @prefix t */
class Test extends Entity {

	private $one;
	private $two;
	/** @column parent_id */
	private $parent_id;

	/** @has_many Test */
	private $children;
}

/** @prefix t2 */
class Test2 extends Entity {

	private $parent_id;

	/** @has_many Test */
	private $children;
}

/** @prefix t3 */
class Test3 extends Entity {

	/** @column parent_id */
	private $xxxent_id;

	/** @has_many Test3 */
	private $children;
}

/** @prefix t4 */
class Test4 extends Entity {

	/** @column xxxent_id */
	private $xxxent_id;

	/** @has_many Test4 */
	private $children;
}

Assert::true( Test::isSelfReferencing() );
Assert::false( Test2::isSelfReferencing() );
Assert::true( Test3::isSelfReferencing() );
Assert::false( Test4::isSelfReferencing() );