<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 29/05/14
 * Time: 15:49
 */

namespace Keboola\Syrup\Service\Queue;

use Aws\Sqs\SqsClient;
use Keboola\Syrup\Exception\ApplicationException;

class QueueService
{
    /**
     * @var SqsClient
     */
    protected $client;
    protected $queueUrl;
    protected $componentName;

    public function __construct(array $config, $componentName)
    {
        $this->client = SqsClient::factory([
            'key'       => $config['access_key'],
            'secret'    => $config['secret_key'],
            'region'    => $config['region']
        ]);
        $this->queueUrl = $config['url'];
        $this->componentName = $componentName;
    }

    /**
     * For backwards compatibility it accepts either ($data, $delay) arguments or ($jobId, $queue, $data, $delay)
     * @param $job array|int
     * @param $data array|int
     * @param $delay int
     * @return int $messageId
     */
    public function enqueue($job, $data = [], $delay = 0)
    {
        if (is_array($job)) {
            if ($data && is_int($data)) {
                $delay = $data;
            }
        } else {
            $job = [
                'jobId' => $job,
                'component' => $this->componentName
            ];

            if (count($data)) {
                $job = array_merge($job, $data);
            }
        }

        $message = $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode($job),
            'DelaySeconds' => $delay,
        ]);
        return $message['MessageId'];
    }

    /**
     * @param int $messagesCount
     * @return array of QueueMessage
     */
    public function receive($messagesCount = 1)
    {
        $result = $this->client->receiveMessage([
            'QueueUrl'          => $this->queueUrl,
            'WaitTimeSeconds'   => 20,
            'VisibilityTimeout' => 3600,
            'MaxNumberOfMessages' => $messagesCount,
        ]);

        $queueUrl = $this->queueUrl;

        return array_map(function($message) use ($queueUrl) {

            $body = json_decode($message['Body']);

            if (!is_object($body)) {
                $this->client->deleteMessage([
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ]);

                throw new ApplicationException("Corrupted message {$message['MessageId']} received. Message was deleted from SQS.", null, [
                    'message' => $message
                ]);
            }

            return new QueueMessage(
                $message['MessageId'],
                $body,
                $message['ReceiptHandle'],
                $queueUrl
            );
        }, (array) $result['Messages']);
    }


    public function deleteMessage(QueueMessage $message)
    {
        $this->client->deleteMessage([
            'QueueUrl' => $message->getQueueUrl(),
            'ReceiptHandle' => $message->getReceiptHandle(),
        ]);
    }
}
