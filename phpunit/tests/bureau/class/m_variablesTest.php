<?php
/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-03-13 at 15:55:59.
 */
class m_variablesTest extends AlterncTest
{
    /**
     * @var m_variables
     */
    protected $object;
    
    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $list = array(
            "testVariable_getNewWayArray"   => "variables-empty.yml",
            "testVariable_getNewWayString"  => "variables-empty.yml",
            "testVariable_getOldWay"        => "variables-empty.yml",
            "default"                       => "variables.yml"
        );
        if (isset($list[$this->getName()])) {
            $dataset_file = $list[$this->getName()];
        } else {
            $dataset_file = "variables.yml";
        }
        return parent::loadDataSet($dataset_file);

    } 

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->object = new m_variables;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @covers m_variables::variable_init
     * @depends testGet_impersonated
     */
    public function testVariable_init($variables)
    {
        $this->object->variable_init();
        global $conf;
        $this->assertTrue(is_array($conf));
    }

    /**
     * @covers m_variables::get_impersonated
     */
    public function testGet_impersonated()
    {
        $variables = $this->object->get_impersonated();
        return $variables;
    }

    /**
     * @covers m_variables::variable_init_maybe
     */
    public function testVariable_init_maybe()
    {
        $this->object->variable_init_maybe();
        $this->assertTrue( (is_array($this->object->cache_conf) && !empty($this->object->cache_conf)) );
    }

    /**
     * @covers m_variables::variable_get
     */
    public function testVariable_get()
    {
        $result                     = $this->object->variable_get("phpunit");
        $this->assertStringMatchesFormat("phpunit",$result);
    }
    
    /**
     * @covers m_variables::variable_get
     */
    public function testVariable_getOldWay()
    {

        $this->object->variable_get('phpunit', 'phpunit-default','phpunit-comment');
        $result                             = $this->object->variable_get('phpunit');
        $this->assertSame("phpunit-default",$result);
        
    }
    
    /**
     * @covers m_variables::variable_get
     */
    public function testVariable_getNewWayString()
    {

        // New way
        $this->object->variable_get('phpunit', 'phpunit-default','comment', array('desc'=>'Want a string','type'=>'string'));
        $result = $this->object->variable_get('phpunit');
        $this->assertSame("phpunit-default",$result);
    }
    
    /**
     * @covers m_variables::variable_get
     */
    public function testVariable_getNewWayArray()
    {
        $phpunitArray = array("ns1"=>'ns1.tld',"ip"=>"1.2.3.4");
        $this->object->variable_get('phpunit', $phpunitArray,'phpunit-comment', array("ns1"=>array('desc'=>'ns name','type'=>'string'),"ip"=>array("desc"=>"here an ip", "type"=>"ip")));
        $result = $this->object->variable_get('phpunit');
        $this->assertSame($phpunitArray,$result);
    }

    /**
     * @covers m_variables::variable_update_or_create
     * @expectedException \Exception
     */
    public function testVariable_create_exception()
    {
        // Insert key with already existing key : success
        $result                     = $this->object->variable_update_or_create("phpunit","phpunit-fail","DEFAULT",0);
    }
    
    /**
     * @covers m_variables::variable_update_or_create
     */
    public function testVariable_create()
    {
        // Insert key with new key : success
        $result                     = $this->object->variable_update_or_create("phpunit-success","phpunit","DEFAULT",0);
        $this->assertTrue($result);
        $this->assertEquals(2, $this->getConnection()->getRowCount('variable'));
        
    }

    /**
     * @covers m_variables::variable_update_or_create
     */
    public function testVariable_update()
    {
        $result                     = $this->object->variable_update_or_create("phpunit","phpunit-updated","DEFAULT",0,999);
        $this->assertTrue($result);
        $this->assertEquals(1, $this->getConnection()->getRowCount('variable'));
        $expectedTable              = $this->loadDataSet("variables-updated.yml")->getTable("variable");
        $currentTable               = $this->getConnection()->createQueryTable('variable', 'SELECT * FROM variable');
        $this->assertTablesEqual($expectedTable, $currentTable);
        
    }

    /**
     * @covers m_variables::del
     */
    public function testDel()
    {
        $result                     = $this->object->del(999);
        $this->assertTrue($result);
        $this->assertEquals(0, $this->getConnection()->getRowCount('variable'));
    }

    /**
     * @covers m_variables::display_valueraw_html
     */
    public function testDisplay_valueraw_html()
    {
        // Empty string
        $empty_result = $this->object->display_valueraw_html(null, "phpunit",FALSE);
        $this->assertStringMatchesFormat("<em>"._("Empty")."</em>",$empty_result);
        // Empty array
        $empty_array_result = $this->object->display_valueraw_html(array(), "phpunit",FALSE);
        $this->assertStringMatchesFormat("<em>"._("Empty array")."</em>",$empty_array_result);
        // String
        $value_result = $this->object->display_valueraw_html("value", "phpunit",FALSE);
        $this->assertStringMatchesFormat($value_result,$value_result);
        // String
        $array_result = $this->object->display_valueraw_html(array("value","value"), "phpunit",FALSE);
        $this->assertStringMatchesFormat("<ul>%s</ul>",$array_result);

    }

    /**
     * @covers m_variables::display_value_html
     * @depends testVariables_list
     */
    public function testDisplay_value_html( $variables )
    {
        $valid_result = $this->object->display_value_html($variables, "DEFAULT", 0, "phpunit",FALSE);
        $this->assertStringMatchesFormat("phpunit",$valid_result);
        
        $invalid_result = $this->object->display_value_html($variables, "DEFAULT", 0, "phpunit-absent",FALSE);
        $this->assertStringMatchesFormat("<em>"._("None defined")."</em>",$invalid_result);
        
    }

    /**
     * @covers m_variables::variables_list_name
     * @todo   Implement testVariables_list_name().
     */
    public function testVariables_list_name()
    {
        $variables = $this->object->variables_list_name();
        $this->assertTrue(is_array($variables));
        return $variables;
    }

    /**
     * @covers m_variables::variables_list
     * @todo   Implement testVariables_list().
     */
    public function testVariables_list()
    {
        $variables = $this->object->variables_list();
        $this->assertTrue(is_array($variables));
        return $variables;
    }
}
