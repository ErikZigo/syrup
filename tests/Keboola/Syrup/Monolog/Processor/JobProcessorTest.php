<?php
/**
 * @package syrup-component-bundle
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Syrup\Tests\Monolog\Processor;

use Keboola\Syrup\Encryption\BaseWrapper;
use Keboola\Syrup\Job\Metadata\Job;
use Keboola\Syrup\Monolog\Processor\JobProcessor;
use Keboola\Syrup\Service\ObjectEncryptor;
use Keboola\Syrup\Test\Monolog\TestCase;

class JobProcessorTest extends TestCase
{

    public function setUp()
    {
        static::bootKernel();
    }

    /**
     * @covers \Keboola\Syrup\Monolog\Processor\JobProcessor::__invoke
     * @covers \Keboola\Syrup\Monolog\Processor\JobProcessor::processRecord
     * @covers \Keboola\Syrup\Monolog\Processor\JobProcessor::setJob
     */
    public function testProcessor()
    {
        $processor = new JobProcessor();
        /** @var ObjectEncryptor $configEncryptor */
        $configEncryptor = self::$kernel->getContainer()->get('syrup.object_encryptor');
        $processor->setJob(new Job($configEncryptor, [
                'id' => uniqid(),
                'runId' => uniqid(),
                'lockName' => uniqid()
            ], null, null, null));
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('job', $record);
        $this->assertArrayHasKey('id', $record['job']);
    }
}
