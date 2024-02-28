<?php

namespace Caylof\Queue;

interface ConsumerInterface
{
    public function getQueue(): string;
    public function handle(array $data): void;
}
