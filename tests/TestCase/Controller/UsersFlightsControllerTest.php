<?php
namespace App\Test\TestCase\Controller;

use App\Controller\UsersFlightsController;
use Cake\TestSuite\IntegrationTestCase;

/**
 * App\Controller\UsersFlightsController Test Case
 */
class UsersFlightsControllerTest extends IntegrationTestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.users_flights',
        'app.flights',
        'app.customers',
        'app.customer_types',
        'app.planes',
        'app.plane_types',
        'app.airports',
        'app.airports_flights',
        'app.users',
        'app.groups'
    ];

    /**
     * Test index method
     *
     * @return void
     */
    public function testIndex()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test view method
     *
     * @return void
     */
    public function testView()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test add method
     *
     * @return void
     */
    public function testAdd()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test edit method
     *
     * @return void
     */
    public function testEdit()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test delete method
     *
     * @return void
     */
    public function testDelete()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
