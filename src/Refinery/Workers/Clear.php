<?php

namespace Emerald\Refinery\Workers;

use Emerald\Refinery\Refinery;
use Emerald\Refinery\Worker;
use Emerald\Refinery\WorkerInterface;
use Emerald\Kernel\Config;
use Emerald\Filesystem\Filesystem;

/**
 * Class Clear
 *
 * @package Emerald\Refinery\Workers
 */
class Clear extends Worker implements WorkerInterface
{

    /**
     *
     */
    public function __construct()
    {
        $this->optional('flush', 'f');

        $this->version = '0.2.0';
    }

    /**
     * If --flush of -f was passed it will completely empty the cache directory
     * otherwise it prunes stale files, and empty directories
     *
     * @return  int
     */
    public function work()
    {
        if (in_array('flush', $this->optionals())) {
            Refinery::hire('Flush');

            $this->say('Hiring Flush', Worker::MESSAGE_DEBUG);

            return Worker::JOB_COMPLETE_OKAY;
        }

        if (!Config::get('cache.driver') === 'files') {
            $this->farewell = Config::get('cache.driver') . ' does not support picking expired cache entries.';
            return Worker::JOB_COMPLETE_OKAY;
        }

        $files = new Filesystem();
        $i = 0;

        foreach ($this->getDirectory(Config::get('cache.directory')) as $tlcd) {

            foreach ($this->getDirectory($tlcd) as $slcd) {

                foreach ($this->getFiles($slcd) as $cachefile) {

                    $time = intval(substr($files->get($cachefile), 0, 10));

                    $this->say("checking {$cachefile}", Worker::MESSAGE_DEBUG);

                    if ($time < time()) {
                        $i++;
                        $this->say("{$cachefile} is stale, deleting", Worker::MESSAGE_DEBUG);

                        $files->delete($cachefile);

                    }

                    if (count($this->getFiles($slcd)) === 0) {
                        $this->say("{$slcd} is empty, deleting", Worker::MESSAGE_DEBUG);

                        $files->delete($slcd);

                    }

                }

                if (count($this->getFiles($tlcd)) === 0) {

                    $this->say("{$tlcd} is empty, deleting", Worker::MESSAGE_DEBUG);

                    $files->delete($tlcd);

                }

            }

        }

        $this->farewell = sprintf('Pruned %d files', $i);

        return Worker::JOB_COMPLETE_OKAY;

    }

    /**
     * @param $directory
     *
     * @return array
     */
    private function getDirectory($directory)
    {
        return glob("{$directory}/*", GLOB_ONLYDIR);
    }

    /**
     * @param $in
     *
     * @return array
     */
    private function getFiles($in)
    {
        return glob("{$in}/*");
    }

} 
