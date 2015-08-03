<?php

namespace Emerald\Refinery;

use Emerald\CLI\CLI;
use Emerald\Filesystem\Filesystem;

/**
 * Class Worker
 *
 * @package Emerald\Refinery
 *
 * @todo  need to add individual worker -h messages
 */
abstract class Worker implements WorkerInterface
{

    const ERR_REASON_UNKNOWN = 0;

    const ERR_REASON_MISSING = 1;

    const JOB_COMPLETE_OKAY  = 0x10;

    const JOB_COMPLETE_FAIL  = 0x20;

    const MESSAGE_DEBUG = 0; // see with -v

    const MESSAGE_INFO  = 1;

    const MESSAGE_LOG   = 2;

    /**
     * @var     array       the hardware actually supplied
     */
    private $hardware = array();

    /**
     * @var     array       the required hardware
     */
    private $required = array();

    /**
     * @var     array       optional hardware the worker excepts
     */
    private $optional = array();

    /**
     * @var     string      The last message printed by {@see WorkerInterface::clockout()}
     */
    protected $farewell = '';

    /**
     * @var 	string
     */
    protected $name     = '';

    /**
     * @var     bool        if the worker works (runnable)
     */
    protected $employed = true;

    /**
     * @var     string
     */
    protected $manager  = 'webteam@motosport.com';

    /**
     * @var     string
     */
    protected $version  = '0.0.1';

    /**
     * Iterates through the supplied arguments, and sets the required's value
     * if it was supplied
     *
     * @param array $onHand
     *
     * @return bool
     */
    public function clockin(array $onHand = array())
    {

        $this
            ->setName()
            ->say("[{$this}]")
            ->optional('quiet',   'q')
            ->optional('verbose', 'v')
        ;

        $missing = array();

        // make sure all required hardware is set
        foreach ($this->required() as $needs => $alias) {

            if (isset($onHand[$needs])) {

                $this->set($needs, $onHand[$needs]);

            } else if (isset($onHand[$alias])) {

                $this->set($needs, $onHand[$alias]);

            } else {

                $missing[] = $needs;

            }
        }

        // if we do have optionals add them to the hardware list
        foreach ($this->optionals() as $option => $value) {

            if (isset($onHand[$option])) {

                $this->set($option, $onHand[$option]);

            } else if (isset($onHand[$value])) {

                $this->set($option, $onHand[$value]);

            }

        }

        if (count($missing) > 0) {

            Refinery::fire(
                get_called_class(),
                $this->getName() . ' was fired for missing required hardware: ' . implode(', ', $missing)
            );

            return false;
        }

        return true;
    }

    /**
     * Set a pid file so it cant run sumltaniously
     */
    public function setPIDFile()
    {
        $files = new Filesystem();

        $pidFile = TMP . '/' . sprintf('%u', crc32($this)) . '.pid';

        $files->put($pidFile, 1);

    }

    /**
     * remove the pid file
     */
    public function removePIDFile()
    {
        $files = new Filesystem();

        $pidFile = TMP . '/' . sprintf('%u', crc32($this)) . '.pid';

        if ($files->exists($pidFile)) {
            $files->delete($pidFile);
        }

    }

    public function clockout()
    {
        $this->say($this->farewell);
    }

    /**
     * @return string
     */
    public function getName()
    {
    	return $this->name;
    }

    /**
     * @return Worker
     */
    public function setName()
    {
    	$this->name = Refinery::name(get_called_class()); // err... str_replace(__NAMESPACE__...)
        return $this;
    }

    /**
     * Is this worker employed? (runnable)
     *
     * @return  bool
     */
    public function employed()
    {
        return $this->employed;
    }

    /**
     * Ask the Refinery to hire a new Worker so you can finish your job
     *
     * @param   string      $on
     * @param   array       $args
     */
    public function depends($on, $args = array())
    {
        Refinery::hire($on, $args);
        Refinery::schedule(Refinery::meeting($on));
    }

    /**
     * Say something
     *
     * @param   string      $words
     * @param   int         $level
     *
     * @return  Worker
     */
    protected function say($words, $level = Worker::MESSAGE_INFO)
    {
        if ($this->get('quiet', false)) return $this;

        if (strpos($words, '[', 0) === false) $words = "  {$words}"; // indent them if its not [<Worker> <Version>]

        if ($level === Worker::MESSAGE_INFO) {
            CLI::write($words);
        } else if ($level === Worker::MESSAGE_DEBUG && $this->get('verbose', false)) {
            CLI::write($words);
        } else if ($level === Worker::MESSAGE_LOG) {
            $this->log($words);
        }

        return $this;
    }

    /**
     * Set the workers required hardware
     *
     * @param   string      $hardware the full name of the option
     * @param   string      $alias    the short name (e.g. -v)
     *
     * @return  Worker
     */
    public function requires($hardware, $alias)
    {
        $this->required = array_merge($this->required, array($hardware => $alias));

        return $this;
    }

    /**
     * Returns all the required hardware
     *
     * @return  array
     */
    protected function required()
    {
        return $this->required;
    }

    /**
     * set an optional piece of hardware
     *
     * @param   string      $hardware the long name (verbose)
     * @param   string      $alias    the short name for the $hardware (-v)
     *
     * @return  Worker
     */
    public function optional($hardware, $alias)
    {
        $this->optional = array_merge($this->optional, array($hardware => $alias));

        return $this;
    }

    /**
     * @return  array
     */
    public function optionals()
    {
        return $this->optional;
    }

    /**
     * Makes sure the worker has the required hardware (args)
     *
     * @param   string      $hardware
     *
     * @return  bool
     */
    public function has($hardware)
    {
        return array_key_exists($hardware, $this->hardware);
    }

    /**
     * Gets a piece hardware if it exists, or the default value is returned
     *
     * @param   string      $hardware
     * @param   mixed       $default
     *
     * @return  mixed
     */
    public function get($hardware, $default = null)
    {
        return ($this->has($hardware)) ? $this->hardware[$hardware] : $default;
    }

    /**
     * Add a piece of hardware
     *
     * @param   string      $hardware
     * @param   mixed       $value
     */
    public function set($hardware, $value)
    {
        $this->hardware[$hardware] = $value;
    }

    /**
     * Fired from the job!
     *
     * @param   string      $message
     * @param   bool|array  $email
     * @param   int         $reason
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function fire($message, $email = false, $reason = Worker::ERR_REASON_UNKNOWN)
    {

        $this->employed = false;

        switch ($reason) {
            case Worker::ERR_REASON_MISSING:
                throw new \InvalidArgumentException($message);
            break;
            case Worker::ERR_REASON_UNKNOWN:
                throw new \Exception($message);
            break;
            default:
                echo "Unknown error has occured.";
            break;
        }

        if ($email !== false) {

            Refinery::memo($this->manager, $email);

        }

    }

    /**
     * @param   string      $message
     *
     * @return  $this
     */
    private function log($message)
    {
        $worker = Refinery::name(get_called_class());
        $log = TMP . "/workers/{$worker}.log";

        $files = new Filesystem();

        $files->append($log, $message);

        return $this;
    }

    /**
     * @return  string
     */
    public function __toString()
    {
        return sprintf('%s %s', $this->getName(), $this->version);
    }

}
