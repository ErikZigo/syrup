<?php

namespace Keboola\Syrup\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Keboola\Syrup\Controller\ApiController;
use Keboola\Syrup\Test\WebTestCase;

/**
 * ApiControllerTest.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 6.6.13
 */

class ApiControllerTest extends WebTestCase
{
    /** @var Client */
    private static $client;

    /** @var ApiController */
    protected $controller;

    /** @var ContainerInterface */
    protected $container;

    public function setUp()
    {
        self::$client = static::createClient();
        $container = static::$client->getContainer();

        $request = Request::create('/syrup/run', 'POST');
        $request->headers->set('X-StorageApi-Token', $container->getParameter('storage_api.test.token'));
        $container->get('request_stack')->push($request);

        $this->controller = new ApiController();
        $this->controller->setContainer($container);

        $this->container = $container;
    }

    public function testInitStorageApi()
    {
        $this->invokeMethod($this->controller, 'initStorageApi');
        $sapiClient = static::readAttribute($this->controller, 'storageApi');
        $this->assertInstanceOf('Keboola\StorageApi\Client', $sapiClient);
    }

    public function testNonExistingBundle()
    {
        static::$client->request(
            'GET',
            '/' . uniqid(),
            [],
            [],
            ['HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token')],
            '{"account":"test"}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('error', $result);
        //@TODO $this->assertEquals('User error', $result['error']);
        $this->assertArrayHasKey('code', $result);
        //@TODO $this->assertEquals(404, $result['code']);
    }

    public function testNonExistingAction()
    {
        static::$client->request(
            'POST',
            '/syrup/' . uniqid(),
            [],
            [],
            ['HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token')],
            '{"account":"test"}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(404, $result['code']);
    }

    public function testRunActionWithoutToken()
    {
        $clientWithoutToken = static::createClient();
        $clientWithoutToken->request(
            'POST',
            '/syrup/run',
            [],
            [],
            [],
            '{"account":"test"}'
        );

        $result = json_decode($clientWithoutToken->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('User error', $result['error']);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(400, $result['code']);
    }

    // basic RunAction test
    public function testRunAction()
    {
        static::$client->request(
            'POST',
            '/syrup/run',
            [],
            [],
            ['HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token')],
            '{}'
        );

        $res = json_decode(static::$client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $res);
        $this->assertArrayHasKey('runId', $res);
        $this->assertArrayHasKey('lockName', $res);
        $this->assertArrayHasKey('project', $res);
        $this->assertArrayHasKey('component', $res);
        $this->assertArrayHasKey('command', $res);
        $this->assertArrayHasKey('params', $res);
        $this->assertArrayHasKey('result', $res);
        $this->assertArrayHasKey('status', $res);
        $this->assertArrayHasKey('process', $res);
        $this->assertArrayHasKey('createdTime', $res);
        $this->assertArrayHasKey('startTime', $res);
        $this->assertArrayHasKey('endTime', $res);
        $this->assertArrayHasKey('durationSeconds', $res);
        $this->assertArrayHasKey('waitSeconds', $res);
        $this->assertArrayHasKey('nestingLevel', $res);
        $this->assertArrayHasKey('isFinished', $res);
        $this->assertArrayHasKey('_index', $res);
        $this->assertArrayHasKey('_type', $res);
        $this->assertArrayHasKey('url', $res);

    }

    // test wrong parameter user error
    public function testRunActionWrongParams()
    {
        try {
            static::$client->request(
                'POST',
                '/syrup/run',
                [],
                [],
                ['HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token')],
                '{"bull":"crap"}'
            );
        } catch (\Exception $e) {
            print $e->getTraceAsString();
            die;
        }

        $result = json_decode(static::$client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('User error', $result['error']);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertArrayHasKey('exceptionId', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('runId', $result);
    }

    public function testEncryptActionSimpleText()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'text/plain'
            ],
            'abcd'
        );

        $result = static::$client->getResponse()->getContent();
        $this->assertEquals("KBC::Encrypted==", substr($result, 0, 16));
    }

    public function testEncryptActionSimpleTextContentType()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'text/plain;charset=UTF-8'
            ],
            'abcd'
        );

        $result = static::$client->getResponse()->getContent();
        $this->assertEquals("KBC::Encrypted==", substr($result, 0, 16));
    }

    public function testEncryptActionSimpleTextAlreadyEncrypted()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'text/plain'
            ],
            'KBC::Encrypted==abcd'
        );

        $result = static::$client->getResponse()->getContent();
        $this->assertEquals("KBC::Encrypted==abcd", $result);
    }

    public function testEncryptActionSimpleJson()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'application/json'
            ],
            '{"key1": "value1", "#key2": "value2"}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey("key1", $result);
        $this->assertArrayHasKey("#key2", $result);
        $this->assertEquals("value1", $result["key1"]);
        $this->assertEquals("KBC::Encrypted==", substr($result["#key2"], 0, 16));
    }

    public function testEncryptActionSimpleJsonContentType()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'application/json; Charset=utf-8'
            ],
            '{"key1": "value1", "#key2": "value2"}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey("key1", $result);
        $this->assertArrayHasKey("#key2", $result);
        $this->assertEquals("value1", $result["key1"]);
        $this->assertEquals("KBC::Encrypted==", substr($result["#key2"], 0, 16));
    }



    public function testEncryptActionSimpleJsonEncrypted()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'application/json'
            ],
            '{"key1": "value1", "#key2": "KBC::Encrypted==abcd", "#key3": "value3"}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey("key1", $result);
        $this->assertArrayHasKey("#key2", $result);
        $this->assertArrayHasKey("#key3", $result);
        $this->assertEquals("value1", $result["key1"]);
        $this->assertEquals("KBC::Encrypted==abcd", $result["#key2"]);
        $this->assertEquals("KBC::Encrypted==", substr($result["#key3"], 0, 16));
    }

    public function testEncryptActionNestedJson()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'application/json'
            ],
            '{"key1": "value1", "key2": {"nestedKey1": "value2", "nestedKey2": {"#finalKey": "value3"}}}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey("key1", $result);
        $this->assertArrayHasKey("key2", $result);
        $this->assertArrayHasKey("nestedKey1", $result["key2"]);
        $this->assertArrayHasKey("nestedKey2", $result["key2"]);
        $this->assertArrayHasKey("#finalKey", $result["key2"]["nestedKey2"]);
        $this->assertEquals("value1", $result["key1"]);
        $this->assertEquals("value2", $result["key2"]["nestedKey1"]);
        $this->assertEquals("KBC::Encrypted==", substr($result["key2"]["nestedKey2"]["#finalKey"], 0, 16));
    }

    public function testEncryptActionNestedJsonAlreadyEncrypted()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'application/json'
            ],
            '{"key1": "value1", "key2": {"nestedKey1": "value2", "nestedKey2": {"#finalKey": "value3", "#finalKeyEncrypted": "KBC::Encrypted==abcd"}}}'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey("key1", $result);
        $this->assertArrayHasKey("key2", $result);
        $this->assertArrayHasKey("nestedKey1", $result["key2"]);
        $this->assertArrayHasKey("nestedKey2", $result["key2"]);
        $this->assertArrayHasKey("#finalKey", $result["key2"]["nestedKey2"]);
        $this->assertEquals("value1", $result["key1"]);
        $this->assertEquals("value2", $result["key2"]["nestedKey1"]);
        $this->assertEquals("KBC::Encrypted==", substr($result["key2"]["nestedKey2"]["#finalKey"], 0, 16));
        $this->assertEquals("KBC::Encrypted==abcd", $result["key2"]["nestedKey2"]["#finalKeyEncrypted"]);
    }

    public function testEncryptActionIncorrectContentType()
    {
        static::$client->request(
            'POST',
            '/syrup/encrypt',
            [],
            [],
            [
                'HTTP_X-StorageApi_Token' => $this->container->getParameter('storage_api.test.token'),
                'CONTENT_TYPE' => 'aa/bb'
            ],
            'abcd'
        );

        $result = json_decode(static::$client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('User error', $result['error']);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertArrayHasKey('exceptionId', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('runId', $result);
        $this->assertEquals('Incorrect Content-Type.', $result["message"]);
    }
}
