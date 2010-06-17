<?php
namespace Test;

/**
 * Test: isqua\Reflection\EntityReflection caching
 *
 * @author     Jan Prachař
 * @category   isqua
 * @package    isqua\Table
 * @subpackage UnitTests
 */

use isqua\Entity;
use isqua\Reflection\EntityReflection;



require __DIR__ . '/../NetteTest/initialize.php';

define('ISQUA_TMP_DIR', __DIR__ . '/tmp');
\NetteTestHelpers::purge(ISQUA_TMP_DIR);



/**
 * @property string $a
 * @property string $b, column="juje a"
 * @property Test\B $ab, belongs_to
 * @property Test\C $ac, has_many, null
 * @property Test\D $ad, has_one
 */
class A extends Entity 
{

}

class B extends Entity {}
class C extends Entity {}
class D extends Entity {}


$ar = EntityReflection::create('Test\A');
ob_start(); dump($ar); $x = ob_get_clean();
unset($ar);

$ar = EntityReflection::create('Test\A');
ob_start(); dump($ar); $y = ob_get_clean();
\Assert::same($x, $y);