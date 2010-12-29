<?php
/**
 * Ugly setup code lifted straight from the Zend manual
 */
abstract class Tree_TestCase extends Zend_Test_PHPUnit_DatabaseTestCase {
    private $_connectionMock;

    /**
     * Name of the xml file dataset to load (no suffix)
     * @var string
     */    
    protected $_dataset = '';

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    protected function getConnection() {
        if($this->_connectionMock == null) {
            $connection = Zend_Db_Table_Abstract::getDefaultAdapter();
            $this->_connectionMock = $this->createZendDbConnection(
                $connection, 'zfunittests'
            );
            Zend_Db_Table_Abstract::setDefaultAdapter($connection);
        }
        return $this->_connectionMock;
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet() {
        if(empty($this->_dataset)) {
            throw new Exception('You must set _dataset. See Tree_TestCase.');   
        }
       
        return $this->createFlatXmlDataSet(
            __DIR__."/../../data/{$this->_dataset}.xml"
        );
    }
    
    public function setUp() {
        parent::setup();
        $this->regions = new Region_Table();
    }
}
