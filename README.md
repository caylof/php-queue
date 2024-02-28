# A simple PHP queue library

used with workerman and redis stream.

## Example

```
example
 - process.php
 - client.php
 - consumers
   - TestPrint.php
```

- make a test consumer

```php
# consumers/TestPrint.php

namespace Caylof\Examples\Consumer;

use Caylof\Queue\ConsumerInterface;

class TestPrint implements ConsumerInterface
{
    public function getQueue(): string
    {
        return 'test1';
    }

    public function handle(array $data): void
    {
        var_dump($data);
    }
}
```


- queue process server

```php
# process.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/consumers/TestPrint.php';

use Workerman\Worker;

Worker::$pidFile = __DIR__ . '/test_queue-pid';
Worker::$logFile = '/dev/null';
Worker::$statusFile = '/dev/null';
$worker = new Worker();
$worker->count = 1;

$worker->onWorkerStart = function(Worker $worker) {
    $redis = new Redis();
    $redis->connect('192.168.110.116', 16379);

    $handler = new \Caylof\Queue\RedisStreamProcess(
        \Illuminate\Container\Container::getInstance(),
        $redis,
        __DIR__ . '/consumers',
        'Caylof\\Examples\\Consumer',
    );
    $handler->onWorkerStart();
};

Worker::runAll();
```

run server:
```shell
php process.php start
```

- client send message

```php
# client.php

require __DIR__ . '/../vendor/autoload.php';

$redis = new Redis();
$redis->connect('192.168.110.116', 16379);

$client = new \Caylof\Queue\RedisStreamClient($redis);
$client->send('test1', ['id'=> 1, 'name' => 'cctv']);
```

run client:
```shell
php client.php
```