<?php

namespace Caylof\Queue;

use Redis;

class RedisStreamClient
{
    public function __construct(
        protected Redis $redis,
    ) {}

    public function send($queue, array $data): void
    {
        $this->redis->xAdd($queue, '*', [
            'data' => json_encode($data),
        ]);
    }
}
