<?php

/**
 * Test: isqua\Entity annotations.
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua
 * @subpackage UnitTests
 */

use isqua\Entity;
use isqua\Nette\AnnotationsParser as A;



require __DIR__ . '/../NetteTest/initialize.php';



/**
 * @table model_test
 * @prefix a
 */
class Test extends Entity {
	static $PREFIX = 'a';

	/**
	 * @null
	 * @column a_main
	 */	
	private $main;
	private $a_alt;
	/** @column wolf */	
	private $sheep;

}



// Class annotations

$rc = new ReflectionClass('Test');
$tmp = A::getAll($rc);

Assert::same( "model_test",  $tmp["table"][0] );


// Property annotations

$rp = $rc->getProperty('main');
$tmp = A::getAll($rp);

Assert::true( $tmp["null"][0] );
Assert::same( $tmp["column"][0], "a_main");

$rp = $rc->getProperty('a_alt');
$tmp = A::getAll($rp);

Assert::null( @$tmp["null"][0] );
Assert::null( @$tmp["column"][0] );

$rp = $rc->getProperty('sheep');
$tmp = A::getAll($rp);

Assert::null( @$tmp["null"][0] );
Assert::same( @$tmp["column"][0], 'wolf');