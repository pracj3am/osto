<?php
namespace osto\DataSource;


/**
 * Datasource for database data definition and fetching
 *
 */
class Database extends \DibiDataSource
{

    private $rowClass = 'DibiRow';
    /** @var \DibiTranslator */
    private $translator;


    public function __construct()
    {
        $args = func_get_args();
        $this->translator = new \DibiTranslator(\dibi::getConnection()->driver);
        parent::__construct($this->translator->translate($args), \dibi::getConnection());
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
        $result->setRowClass(array($this->rowClass,'createFromValues'));
        return $result;
    }



    /**
     * Joins table to SQL
     * @param string $sql SQL of joined table
     */
    public function join($sql)
    {
        $this->setSql($this->sql . ' JOIN ' . $sql);
    }



    /**
     * Sets SQL
     * @param string $sql
     */
    public function setSql($sql)
    {
        $this->sql = $this->translator->translate((array)$sql);
        $this->result = $this->count = $this->totalCount = NULL;
    }



    /**
     * Returns SQL
     * @return string SQL
     */
    public function getSql()
    {
        return $this->sql;
    }
}