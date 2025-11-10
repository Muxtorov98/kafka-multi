<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use RdKafka\Conf;
use RdKafka\Producer as RdProducer;
use RdKafka\TopicConf;

final class Producer
{
    private RdProducer $producer;

    public function __construct(private KafkaOptions $options)
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->options->brokers);

        foreach ($this->options->producer as $key => $value) {
            $conf->set((string)$key, (string)$value);
        }

        // SASL / SSL Security (optional)
        if (!empty($this->options->security['protocol'])) {
            $conf->set('security.protocol', $this->options->security['protocol']);
        }

        if (!empty($this->options->security['sasl'])) {
            foreach ($this->options->security['sasl'] as $k => $v) {
                $conf->set("sasl.$k", (string)$v);
            }
        }

        $this->producer = new RdProducer($conf);
    }

    /**
     * SYNC SEND
     */
    public function send(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->produceMessage($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
        $this->flush();
    }

    /**
     * BATCH SEND (sync)
     *
     * @param array<int, array> $messages
     */
    public function sendBatch(string $topic, array $messages, ?string $key = null, array $headers = []): void
    {
        foreach ($messages as $payload) {
            $this->produceMessage($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
        }
        $this->flush();
    }

    /**
     * ASYNC SEND (Fire & Forget)
     * Flush kutmaydi — juda tez ishlash uchun
     */
    public function sendAsync(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->produceMessage($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
        $this->producer->poll(0);
    }

    /**
     * SEND WITH CALLBACK
     */
    public function sendWithCallback(
        string $topic,
        array $payload,
        callable $onSuccess,
        ?callable $onError = null,
        ?string $key = null,
        array $headers = []
    ): void {
        try {
            $this->produceMessage($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
            $this->flush();
            $onSuccess($payload);
        } catch (\Throwable $e) {
            if ($onError) {
                $onError($e, $payload);
                return;
            }
            throw $e;
        }
    }

    /**
     * SEND TO SPECIFIC PARTITION
     */
    public function sendToPartition(
        string $topic,
        int $partition,
        array $payload,
        ?string $key = null,
        array $headers = []
    ): void {
        $this->produceMessage($topic, $partition, $payload, $key, $headers);
        $this->flush();
    }

    private function produceMessage(string $topic, int $partition, array $payload, ?string $key, array $headers): void
    {
        $topicConf = new TopicConf();
        $rdTopic = $this->producer->newTopic($topic, $topicConf);

        $msg = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $rdTopic->producev(
            $partition,
            0,
            $msg,
            $key,
            $headers ?: null
        );

        $this->producer->poll(0);
    }

    private function flush(): void
    {
        $result = $this->producer->flush(1000);
        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new \RuntimeException('Kafka flush failed: ' . $result);
        }
    }
}