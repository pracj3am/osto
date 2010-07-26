<?php
namespace osto\DataSource;


/**
 * Datasource for database data definition and fetching
 *
 */
class Database extends \DibiDataSource
{


    public function __construct()
    {
        $args = func_get_args();
		$translator = new \DibiTranslator(\dibi::getConnection()->driver);
        parent::__construct($translator->translate($args), \dibi::getConnection());
    }



	/**
	 * Returns (and queries) DibiResult.
	 * @return \DibiResult
	 */
	public function getResult()
	{
		$result = parent::getResult();
        $result->setRowClass($class);
		return $result;
	}

}