<?php
namespace osto\DataSource;


/**
 * Datasource for database data definition and fetching
 *
 */
class Database extends \DibiDataSource
{

    private $rowClass = 'DibiRow';


    public function __construct()
    {
        $args = func_get_args();
		$translator = new \DibiTranslator(\dibi::getConnection()->driver);
        parent::__construct($translator->translate($args), \dibi::getConnection());
    }



    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;
    }


	/**
	 * Returns (and queries) DibiResult.
	 * @return \DibiResult
	 */
	public function getResult()
	{
		$result = parent::getResult();
        $result->setRowClass($this->rowClass);
		return $result;
	}

}