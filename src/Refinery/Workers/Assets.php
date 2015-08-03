<?php

namespace Emerald\Refinery\Workers;

use Emerald\Refinery\Worker;
use Emerald\Refinery\WorkerInterface;

/**
 * Class Assets
 *
 * @package Emerald\Refinery\Workers
 */
class Assets extends Worker implements WorkerInterface
{

    /**
     * @param $onHand
     */
    public function __construct($onHand)
    {
        $this->optionals('task', 't');

        $this->manager = 'devon.hazelett@motosport.com';
        $this->version = '0.1.1';

    }

    /**
     * {Insert you workers job description}
     *
     * @return  bool        true if the task finished OKAY
     */
    public function work()
    {
        $this->farewell = `node & cd public/ & gulp {$this->get('task', '')} & cd ..`;

        return Worker::JOB_COMPLETE_OKAY;
    }

}
