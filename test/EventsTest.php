<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use PHPUnit\Framework\TestCase;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * Event test
 *
 * @vendor   Cradle
 * @package  Model
 * @author   John Doe <john@acme.com>
 */
class Cradle_History_EventsTest extends TestCase
{
    /**
     * @var Request $request
     */
    protected $request;

    /**
     * @var Request $response
     */
    protected $response;

    /**
     * @var int $id
     */
    protected static $id;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->request = new Request();
        $this->response = new Response();

        $this->request->load();
        $this->response->load();
    }

    /**
     * history-create
     *
     * @covers Cradle\Module\System\Model\Validator::getCreateErrors
     * @covers Cradle\Module\System\Model\Validator::getOptionalErrors
     * @covers Cradle\Package\System\Model\Service\SqlService::create
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::create
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::createDetail
     */
    public function testHistoryCreate()
    {
        $this->request->setStage([
            'history_remote_address' => '127.0.0.1',
            'history_activity' => 'Test',
            'history_page' => '/',
            'history_meta' => [],
            'history_flag' => '0',
            'history_active' => '1',
            'profile_id' => '1',
        ]);

        cradle()->trigger('history-create', $this->request, $this->response);

        $this->assertEquals('Test', $this->response->getResults('history_activity'));
        self::$id = $this->response->getResults('history_id');
        $this->assertTrue(is_numeric(self::$id));
    }

    /**
     * history-detail
     *
     * @covers Cradle\Module\System\Model\Validator::getCreateErrors
     * @covers Cradle\Module\System\Model\Validator::getOptionalErrors
     * @covers Cradle\Package\System\Model\Service\SqlService::create
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::create
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::createDetail
     */
    public function testHistoryDetail()
    {
        $this->request->setStage([
            'history_id' => 1
        ]);

        cradle()->trigger('history-detail', $this->request, $this->response);
        $this->assertEquals(1, $this->response->getResults('history_id'));
    }

    /**
     * history-remove
     *
     * @covers Cradle\Package\System\Model\Service\SqlService::get
     * @covers Cradle\Package\System\Model\Service\SqlService::update
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::remove
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::removeDetail
     */
    public function testHistoryRemove()
    {
        $this->request->setStage([
            'history_id' => self::$id
        ]);

        cradle()->trigger('history-remove', $this->request, $this->response);
        $this->assertEquals(self::$id, $this->response->getResults('history_id'));
    }

    /**
     * history-restore
     *
     * @covers Cradle\Package\System\Model\Service\SqlService::get
     * @covers Cradle\Package\System\Model\Service\SqlService::update
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::remove
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::removeDetail
     */
    public function testHistoryRestore()
    {
        $this->request->setStage([
            'history_id' => self::$id
        ]);

        cradle()->trigger('history-restore', $this->request, $this->response);
        $this->assertEquals(self::$id, $this->response->getResults('history_id'));
    }

    /**
     * history-search
     *
     * @covers Cradle\Package\System\Model\Service\SqlService::search
     * @covers Cradle\Package\System\Model\Service\ElasticService::search
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::getSearch
     */
    public function testHistorySearch()
    {
        $this->request->setStage([
            'order' => ['history_id' => 'ASC']
        ]);

        cradle()->trigger('history-search', $this->request, $this->response);

        $actual = $this->response->getResults();

        $this->assertArrayHasKey('rows', $actual);
        $this->assertArrayHasKey('total', $actual);
    }

    /**
     * history-update
     *
     * @covers Cradle\Package\System\Model\Service\SqlService::get
     * @covers Cradle\Package\System\Model\Service\SqlService::update
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::remove
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::removeDetail
     */
    public function testHistoryUpdate()
    {
        $this->request->setStage([
            'history_id' => self::$id,
            'history_activity' => 'New Test Activity'
        ]);

        cradle()->trigger('history-update', $this->request, $this->response);
        $this->assertEquals('New Test Activity', $this->response->getResults('history_activity'));
        $this->assertEquals(self::$id, $this->response->getResults('history_id'));
    }

    /**
     * history-update
     *
     * @covers Cradle\Package\System\Model\Service\SqlService::get
     * @covers Cradle\Package\System\Model\Service\SqlService::update
     * @covers Cradle\Module\System\Utility\Service\AbstractElasticService::remove
     * @covers Cradle\Module\System\Utility\Service\AbstractRedisService::removeDetail
     */
    public function testHistoryMarkAsRead()
    {
        cradle()->trigger('history-mark-as-read', $this->request, $this->response);

        $this->assertTrue(!empty($this->response->getResults()));
    }
}
