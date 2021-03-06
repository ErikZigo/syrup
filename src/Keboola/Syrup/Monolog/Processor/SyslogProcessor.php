<?php

namespace Keboola\Syrup\Monolog\Processor;

use Keboola\StorageApi\Exception as SapiException;
use Keboola\Syrup\Debug\Exception\FlattenException;
use Keboola\Syrup\Debug\ExceptionHandler;
use Keboola\Syrup\Aws\S3\Uploader;
use Keboola\Syrup\Exception\SyrupComponentException;
use Keboola\Syrup\Service\StorageApi\StorageApiService;

/**
 * Injects info about component and used Storage Api token
 */
class SyslogProcessor
{

    private $componentName;
    private $tokenData;
    private $runId;

    /**
     * @var Uploader
     */
    private $s3Uploader;

    public function __construct($componentName, StorageApiService $storageApiService, Uploader $s3Uploader)
    {
        $this->componentName = $componentName;
        $this->s3Uploader = $s3Uploader;
        try {
            // does not work for some commands
            $storageApiClient = $storageApiService->getClient();
            $this->tokenData = $storageApiClient->getLogData();
            $this->runId = $storageApiClient->getRunId();
        } catch (SyrupComponentException $e) {
        } catch (SapiException $e) {
        }
    }

    public function setRunId($runId)
    {
        $this->runId = $runId;
    }

    public function setTokenData($tokenData)
    {
        $this->tokenData = $tokenData;
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        return $this->processRecord($record);
    }

    public function processRecord(array $record)
    {
        if (empty($record['component'])) {
            $record['component'] = $this->componentName;
        }
        $record['runId'] = $this->runId;
        $record['pid'] = getmypid();
        $record['priority'] = $record['level_name'];

        if ($this->tokenData) {
            $record['token'] = [
                'id' => $this->tokenData['id'],
                'description' => $this->tokenData['description'],
                'token' => $this->tokenData['token'],
                'owner' => [
                    'id' => $this->tokenData['owner']['id'],
                    'name' => $this->tokenData['owner']['name']
                ]
            ];
        }

        if (isset($record['context']['exceptionId'])) {
            $record['exceptionId'] = $record['context']['exceptionId'];
        }
        if (isset($record['context']['exception'])) {
            /** @var \Exception $e */
            $e = $record['context']['exception'];
            if ($e instanceof \Exception) {
                $flattenException = FlattenException::create($e);
                $eHandler = new ExceptionHandler(true);
                $html = $eHandler->getHtml($flattenException);
                $record['exception'] = [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'attachment' => $this->s3Uploader->uploadString('exception', $html, 'text/html')
                ];
            }
        }

        $json = json_encode($record);
        if (strlen($json) < 1024) {
            return $record;
        } else {
            $record['attachment'] = $this->s3Uploader->uploadString('log', $json, 'text/json');
            if (mb_strlen($record['message']) > 256) {
                $record['message'] = mb_substr($record['message'], 0, 256);
            }
            $allowedFields = ['message', 'component', 'runId', 'pid', 'priority', 'level', 'attachment',
                'exception', 'exceptionId', 'token', 'cliCommand', 'http', 'job', 'app'];
            foreach (array_keys($record) as $fieldName) {
                if (!in_array($fieldName, $allowedFields)) {
                    unset($record[$fieldName]);
                }
            }
            if (isset($record['http'])) {
                $allowedFields = ['url', 'userAgent', 'ip'];
                foreach (array_keys($record['http']) as $fieldName) {
                    if (!in_array($fieldName, $allowedFields)) {
                        unset($record['http'][$fieldName]);
                    }
                }
            }
            if (isset($record['job'])) {
                $allowedFields = ['id'];
                foreach (array_keys($record['job']) as $fieldName) {
                    if (!in_array($fieldName, $allowedFields)) {
                        unset($record['job'][$fieldName]);
                    }
                }
            }
            return $record;
        }
    }
}
