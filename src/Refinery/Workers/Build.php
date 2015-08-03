<?php

namespace Emerald\Refinery\Workers;

use Emerald\Refinery\Refinery;
use Emerald\Refinery\Worker;
use Emerald\Refinery\WorkerInterface;

/**
 * Class Autoload
 *
 * @package Emerald\Refinery\Workers
 */
class Build extends Worker implements WorkerInterface
{

    private $prints = false;

    /**
     * @param   $onHand
     */
    public function __construct($onHand)
    {
        $this
            ->optional('all', 'all')
            ->optional('sanity', 'sanity')
            ->optional('autoload', 'autoload')
            ->optional('assets', 'assets')
            ->optional('task', 't')
        ;

    }

    /**
     * {Insert you workers job description}
     *
     * @return  bool        true if the task finished OKAY
     */
    public function work()
    {
        $this->prints = $this->has('verbose');

        $this->say('Building Emerald', Worker::MESSAGE_DEBUG);

        if (!$this->has('all') && count($this->optionals()) === 0) {
            $this->set('all', true);
        }

        if ($this->has('all') || $this->has('sanity')) {

            Refinery::hire('StandardsCheck', array(
                'directory' => 'lib',
                'verbose'   => $this->prints
            ));

        }

        if ($this->has('all') || $this->has('autoload')) {

            // Build the map file
            Refinery::hire('Mapper', array(
                'directory' => 'lib',
                'output'    => APP . '/config/map.php',
                'verbose'   => $this->prints
            ));

            // build the alias file
            Refinery::hire('Aliaser', array(
                'directory' => 'lib',
                'output'    => APP . '/config/aliases.php',
                'verbose'   => $this->prints

            ));
        }

        if ($this->has('all') || $this->has('assets')) {

            Refinery::hire('Assets', array(
                'task' => $this->get('task', '')
            ));

        }

        return Worker::JOB_COMPLETE_OKAY;
    }


} 
