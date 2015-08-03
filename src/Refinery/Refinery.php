<?php

namespace Emerald\Refinery;

use Emerald\CLI\CLI;

/**
 * Class Refinery
 *
 * @package Emerald\Refinery
 */
class Refinery
{

    const VERSION = '0.3.0';

    const OPERATION_COMPLETE_OKAY = 0x10;
    const OPERATION_COMPLETE_FAIL = 0x20;

    /**
     * @var     Worker[]
     */
    public static $workers = array();

    private static $complete = array();

    /**
     * @var     Worker
     */
    private static $worker;

    /**
     * Start the factory up
     */
    public static function open()
    {
        $argv = $_SERVER['argv'];

        array_shift($argv);

        static::$worker = array_shift($argv);

        // Must boot the CLI so we have access to STD*
        CLI::boot($argv);

        if (static::$worker === null) {
            return Refinery::OPERATION_COMPLETE_FAIL;
        } else if (CLI::option('help') || CLI::option('h')) {
            // Just return, a worker is not set thus !open === usage()
            return Refinery::OPERATION_COMPLETE_OKAY;
        }

        return Refinery::OPERATION_COMPLETE_OKAY;

    }

    /**
     * Assure the factory has something to do
     *
     * @return  bool
     */
    public static function closed()
    {
        return static::$worker === null; // count(static::$workers) <= 0;
    }

    /**
     * Run the daily operations
     *
     * @return void
     */
    public static function operate()
    {

        static::hire(static::$worker, CLI::option());

        static::close();

    }

    /**
     *
     */
    public static function close()
    {
        CLI::write(sprintf("[Refinery]\n All workers have finished for the day.\n  %s", implode("\n  ", static::$complete)));
    }

    /**
     * Hire a new worker
     *
     * @param   string      $position
     * @param   array       $hardware
     *
     * @return  int
     */
    public static function hire($position, $hardware = array())
    {
        $location = __DIR__ . "/Workers/{$position}.php";

        // class_exists triggers autoloader...
        try {
            if (file_exists($location)) {
                $title = __NAMESPACE__ . "\\Workers\\{$position}";
            } else if (class_exists($position)) {
                $title = $position;
            } else {
                $title = 'stdClass';
            }
        } catch (\UnexpectedValueException $e) {
            CLI::write("{$position} is not an available worker.");
            CLI::set_option('h', true); // hack to hide the "no worker specified message"
            return Refinery::OPERATION_COMPLETE_FAIL;
        }

        /**
         * @fixme clockin should be protected, but a bug setting this
         *        is preventing Refinery::fire() from seeing that
         *        $position is set.
         * @todo  check the functionality in php 5.4
         * @var   Worker      $worker
         */
        $worker = (static::$workers[static::name($position)] = new $title($hardware));

        if ($worker->clockin($hardware)) {

            $worker->setPIDFile();

            static::schedule($worker);

            return Refinery::OPERATION_COMPLETE_OKAY;
        }

        return Refinery::OPERATION_COMPLETE_FAIL;

    }

    /**
     * Remove a worker from the factory
     *
     * @param   string      $position
     * @param   string      $reason
     */
    public static function fire($position, $reason)
    {
        // namespace removal
        $position = static::name($position);
        $worker = (isset(static::$workers[$position])) ? static::$workers[$position] : false;

        if ($worker instanceof Worker) {

            $email = array(
                'subject' => "{$worker->getName()} has been fired",
                'body'    => $reason
            );

            $worker->fire($reason, $email);

            unset(static::$workers[$position]);

        }

    }

    /**
     * Schedule a worker
     *
     * @param Worker $worker
     *
     * @return bool
     */
    public static function schedule(Worker $worker)
    {
        // verify their visa
        if ($worker->employed() && $worker->work()) {

            $worker->clockout();

            $worker->removePIDFile(); // after clockout, some workers have log reports

            static::$complete[] = $worker->getName();

            return true;

        }

        return false;

    }

    /**
     * Meet a worker
     *
     * @param $position
     *
     * @return null
     */
    public static function meeting($position)
    {
        return isset(static::$workers[$position]) ? static::$workers[$position] : null;
    }

    /**
     * Send manager(s) a memo
     *
     * @param mixed     $manager
     * @param array     $email
     */
    public static function memo($manager, $email)
    {

        if ($manager !== null) {

            if (is_array($manager)) {

                $to = implode(', ', $manager);

            } else {

                $to = $manager;

            }

        } else if (isset($email['to'])) {

            if (is_array($email['to'])) {

                $to = implode(', ', $email['to']);

            } else {

                $to = $email['to'];

            }

        } else {

            $to = 'webteam@motosport.com';

        }

        if (!isset($email['headers'])) {

            $email['headers'] = null;

        }

        if (ini_get('sendmail_from') === '' && stripos($email['headers'], 'from:') === false) {
            $email['headers'] .= 'From: refinery@motosport.com';
        }

        CLI::write("Emailing {$to}");
        mail($to, $email['subject'], $email['body'], $email['headers']);
    }

    /**
     * @return  string      prints process info
     */
    public static function signature()
    {
        return sprintf("%s %s, %s/%s, %s", static::name(), Refinery::VERSION, get_current_user(), getmypid(), php_uname());
    }

    public static function usage()
    {
        if (CLI::option('h') === null) {
            CLI::write('No worker specified');
        }

        CLI::write('  [Usage]');
        CLI::write('    refinery <worker> [hardware]');
        CLI::write('  [Available Workers]');

        foreach (glob(__DIR__ . '/Workers/*.php') as $worker) {

            $worker = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $worker));
            $worker = str_replace('.php', '', end($worker));

            CLI::write("    {$worker}");

        }
    }

	/**
	 * Use to return a workers base name (no namespace)
	 *
	 * @param  string  		$name
	 *
	 * @return string
	 */
    public static function name($name = null)
    {
    	$name = ($name === null) ? __CLASS__ : $name;

    	if (preg_match('/\\\\([\w]+)$/', $name, $match)) {
    		$name = $match[1];
    	}

    	return $name;
    }

}
