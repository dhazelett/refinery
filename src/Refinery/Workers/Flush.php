<?php

namespace Emerald\Refinery\Workers;

use Emerald\Cache\Cache;
use Emerald\Cache\Connectors\MemcachedConnector;
use Emerald\Cache\Modules\APCStorage;
use Emerald\Cache\Modules\ArrayStorage;
use Emerald\Cache\Modules\FileStorage;
use Emerald\Cache\Modules\MemcachedStorage;
use Emerald\Refinery\Worker;
use Emerald\Refinery\WorkerInterface;
use Emerald\Filesystem\Filesystem;
use Emerald\Kernel\Config;
use Emerald\Cache\Connectors\APC;

/**
 * Class Flush
 *
 * @package Emerald\Refinery\Workers
 */
class Flush extends Worker implements WorkerInterface
{

    /**
     * @var     \Emerald\Cache\StorageInterface
     */
    private $driver;

    /**
     *
     */
    public function __construct()
    {
        $this->greeting = Config::get('cache.driver') . 'Cache Flush';
    }

    /**
     * @return int
     */
    public function work()
    {
        $settings = Config::get('cache');

        if ($settings['driver'] === 'array') {
            $this->say("Array driver does not need flushing, all done.", Worker::MESSAGE_DEBUG);
            return Worker::JOB_COMPLETE_OKAY;
        }

        switch ($settings['driver']) {
            case 'apc':
                $this->driver = new APCStorage(new APC());
            break;
            case 'files':
                $this->driver = new FileStorage(new Filesystem(), $settings['directory']);
            break;
            case 'memcached':
                $memcached = new MemcachedConnector($settings['cache.memcached']);
                $this->driver = new MemcachedStorage($memcached->getMemecached());
            break;
        }

        $cache = new Cache($this->driver, $settings['hash']);

        $cache->flush();

        $this->farewell = 'Cache has been flushed';

        return Worker::JOB_COMPLETE_OKAY;

    }

} 
