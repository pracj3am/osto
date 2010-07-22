<?php
namespace osto;


/**
 * Datasource for further data definition and fetching
 *
 */
class DataSource extends \DibiDataSource
{


    public function __construct($args)
    {
		$translator = new DibiTranslator($this->driver);
        parent::__construct($translator->translate($args), dibi::getConnection());
    }



	/**
	 * Returns (and queries) DibiResult.
	 * @return DibiResult
	 */
	public function getResult()
	{
		$result = parent::getResult();
        $result->setRowClass($class);
		return $result;
	}

}