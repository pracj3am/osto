<?php

/**
 * Test: osto\Entity invoke
 *
 * @author     Jan Prachař
 * @category   osto
 * @package    osto
 * @subpackage UnitTests
 */

namespace {

    use osto\Entity;



    require __DIR__ . '/../NetteTest/initialize.php';

    define('OSTO_TMP_DIR', __DIR__ . '/tmp');
    NetteTestHelpers::purge(OSTO_TMP_DIR);


    db_connect();


    /**
     * @property int $a
     */
    class A extends Entity
    {

    }

}

namespace Test {
    use osto\Entity;

    class A extends Entity
    {

    }
}

namespace {
    A::register();
    Test\A::register();


    output(A());

    output(Test\A('[a_a] = %s', 'b'));

    $a = new A();

    output($a('[a_id] = ', 6));

    $a = new Test\A();

    output($a());

    
    __halt_compiler();
}
------EXPECT------

			SELECT *
			FROM `a` AS `$a`





			SELECT *
			FROM `a` AS `$a`
			 WHERE (`a_a` = 'b')




			SELECT *
			FROM `a` AS `$a`
			 WHERE (`a_id` =  6)




			SELECT *
			FROM `a` AS `$a`

