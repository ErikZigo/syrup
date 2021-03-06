<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 19/03/15
 * Time: 11:49
 */

namespace Keboola\Syrup\Test;

use Keboola\Syrup\Elasticsearch\JobMapper;
use Keboola\Syrup\Job\Metadata\Job;
use Keboola\Syrup\Job\Metadata\JobFactory;
use Keboola\Syrup\Service\ObjectEncryptor;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Keboola\StorageApi\Client as StorageApiClient;

class CommandTestCase extends WebTestCase
{
    /** @var Application */
    protected $application;

    protected $storageApiToken;

    /** @var StorageApiClient */
    protected $storageApiClient;

    /** @var JobMapper $jobMapper */
    protected $jobMapper;

    /** @var JobFactory $jobFactory */
    protected $jobFactory;

    protected function setUp()
    {
        $this->bootKernel();

        $this->application = new Application(self::$kernel);

        $this->storageApiToken = self::$kernel
            ->getContainer()
            ->getParameter('storage_api.test.token');

        $this->storageApiClient = new StorageApiClient([
            'token' => $this->storageApiToken,
            'url' => self::$kernel
                ->getContainer()
                ->getParameter('storage_api.test.url')
        ]);

        $this->jobMapper = self::$kernel
            ->getContainer()
            ->get('syrup.elasticsearch.current_component_job_mapper');

        $this->jobFactory = self::$kernel
            ->getContainer()
            ->get('syrup.job_factory');
        $this->jobFactory->setStorageApiClient($this->storageApiClient);
    }

    protected function createJob()
    {
        $encryptedToken = self::$kernel
            ->getContainer()
            ->get('syrup.encryptor')
            ->encrypt($this->storageApiToken);

        /** @var ObjectEncryptor $configEncryptor */
        $configEncryptor = self::$kernel->getContainer()->get('syrup.object_encryptor');
        return new Job($configEncryptor, [
                'id' => $this->storageApiClient->generateId(),
                'runId' => $this->storageApiClient->generateId(),
                'project' => [
                    'id' => '123',
                    'name' => 'Syrup TEST'
                ],
                'token' => [
                    'id' => '123',
                    'description' => 'fake token',
                    'token' => $encryptedToken
                ],
                'component' => 'syrup',
                'command' => 'run',
                'params' => [],
                'process' => [
                    'host' => gethostname(),
                    'pid' => getmypid()
                ],
                'createdTime' => date('c')
            ], null, null, null);
    }
}
