<?php
/**
 * @package syrup
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\Syrup\Tests\Job\Metadata;

use Keboola\StorageApi\Client;
use Keboola\Syrup\Encryption\Encryptor;
use Keboola\Syrup\Job\Metadata\JobFactory;

class JobFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers \Keboola\Syrup\Job\Metadata\JobFactory::create
     * @covers \Keboola\Syrup\Job\Metadata\JobFactory::setStorageApiClient
     */
    public function testJobFactory()
    {
        $storageApiClient = new Client([
            'token' => SYRUP_SAPI_TEST_TOKEN,
            'userAgent' => SYRUP_APP_NAME,
        ]);

        $key = md5(uniqid());
        $encryptor = new Encryptor($key);
        $jobFactory = new JobFactory(SYRUP_APP_NAME, $encryptor);
        $jobFactory->setStorageApiClient($storageApiClient);

        $command = uniqid();
        $param = uniqid();
        $lock = uniqid();
        $tokenData = $storageApiClient->verifyToken();

        $job = $jobFactory->create($command, ['param' => $param], $lock);

        $this->assertEquals($command, $job->getCommand());
        $this->assertEquals(SYRUP_APP_NAME, $job->getComponent());
        $this->assertEquals(SYRUP_APP_NAME, $job->getApp());
        $this->assertEquals($lock, $job->getLockName());
        $this->assertEquals(['param' => $param], $job->getParams());
        $this->assertArrayHasKey('id', $job->getProject());
        $this->assertEquals($tokenData['owner']['id'], $job->getProject()['id']);
        $this->assertArrayHasKey('name', $job->getProject());
        $this->assertEquals($tokenData['owner']['name'], $job->getProject()['name']);
        $this->assertArrayHasKey('id', $job->getToken());
        $this->assertEquals($tokenData['id'], $job->getToken()['id']);
        $this->assertArrayHasKey('description', $job->getToken());
        $this->assertEquals($tokenData['description'], $job->getToken()['description']);
        $this->assertArrayHasKey('token', $job->getToken());
        $this->assertEquals($tokenData['token'], $encryptor->decrypt($job->getToken()['token']));
    }
}
