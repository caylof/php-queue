<?php

namespace Caylof\Queue;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Redis;
use Throwable;
use Workerman\Timer;

class RedisStreamProcess
{
    public function __construct(
        protected ContainerInterface $container,
        protected Redis $redis,
        protected LoggerInterface $logger,
        protected string $consumerDir,
        protected string $consumerNamespace,
    ) {}

    public function onWorkerStart(): void
    {
        $streams = [];
        $handlers = [];
        foreach ($this->retrieveConsumer() as $consumer) {
            $this->redis->xGroup('CREATE', $consumer->getQueue(), 'g', '0', true);
            $streams[$consumer->getQueue()] = '>';
            $handlers[$consumer->getQueue()] = $consumer->handle(...);
        }
        if (!empty($streams)) {
            Timer::add(0.05, function() use ($streams, $handlers) {
                $this->consume('g', 'c', $streams, $handlers);
            });
        }
    }

    /**
     * @return array<int, ConsumerInterface>
     */
    protected function retrieveConsumer(): array
    {
        $consumers = [];
        $dir = new \DirectoryIterator($this->consumerDir);
        $dir->rewind();
        foreach ($dir as $file) {
            if (!$file->isDot() && $file->isFile()) {
                $consumer = $this->container->get($this->consumerNamespace . '\\'. $file->getBasename('.php'));
                if ($consumer instanceof ConsumerInterface) {
                    $consumers[] = $consumer;
                }
            }
        }
        return $consumers;
    }

    protected function consume(string $groupName, string $consumerName, array $streams, array $handlers): void
    {
        $groupMsg = $this->redis->xReadGroup($groupName, $consumerName, $streams, 1, 50000);
        foreach ($groupMsg as $key => $messages) {
            foreach ($messages as $msgId => $package) {
                try {
                    $handlers[$key](json_decode($package['data'], true));
                } catch (Throwable $e) {
                    $this->logger->error($e->getMessage(), [
                        'queue' => $key,
                        'data' => $package['data'],
                        'error' => $e->getFile() . ':' . $e->getLine(),
                    ]);
                } finally {
                    $this->redis->multi()
                        ->xAck($key, $groupName, [$msgId])
                        ->xDel($key, [$msgId])
                        ->exec();
                }
            }
        }
    }
}
