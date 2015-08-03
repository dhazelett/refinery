<?php

namespace Emerald\Refinery;

/**
 * Interface WorkerInterface
 *
 * @package Emerald\Refinery
 */
interface WorkerInterface
{

    /**
     * {Insert you workers job description}
     *
     * @return  bool        true if the task finished OKAY
     */
    public function work();

}
