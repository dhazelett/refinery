<?php

namespace Emerald\Refinery\Workers;

use Emerald\Filesystem\Filesystem;
use Emerald\Refinery\Worker;
use Emerald\Refinery\WorkerInterface;

/**
 * Class StandardsCheck
 *
 * @package Emerald\Refinery\Workers
 */
class StandardsCheck extends Worker implements WorkerInterface
{

    /**
     * @var     \SplFileInfo[]
     */
    private $iterator;

    /**
     * @var     \Emerald\Filesystem\Filesystem
     */
    private $files;

    /**
     * @param   array       $onHand
     */
    public function __construct($onHand)
    {
        $this->requires('directory', 'd');

        $this->iterator = $this->getIterator();

        $this->files = new Filesystem();
    }

    /**
     * {Insert you workers job description}
     *
     * @return  bool        true if the task finished OKAY
     */
    public function work()
    {
        $this
            // ->fixFileExtensions()
            ->findMultiClassFiles()
            ->findMisMatchClassFileNames()
            ->findUpperClass()
        ;

        return Worker::JOB_COMPLETE_OKAY;
    }

    /**
     * @return  \RegexIterator
     */
    private function getIterator()
    {
        return new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->get('directory', LIB)),
                \RecursiveIteratorIterator::SELF_FIRST
            ),
            '{^.+\.php$}',
            \RecursiveRegexIterator::MATCH
        );
    }

    /**
     * Automagically fixes file extensions for the autoloader
     *
     * @return StandardsCheck
     */
    private function fixFileExtensions()
    {

        $this->say('File Extensions', Worker::MESSAGE_DEBUG);

        foreach ($this->iterator as $path => $SplFileInfo) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            $fix  = str_replace('.cls.php', '.php', $path);

            $this->say("Renaming {$path} to {$fix}", Worker::MESSAGE_DEBUG);

            rename($path, $fix);
        }

        return $this;

    }

    /**
     * @return  StandardsCheck
     */
    public function findMultiClassFiles()
    {
        $this->say('MultiClass', Worker::MESSAGE_DEBUG);

        $countFiles = 0;
        $countMulti = 0;
        $files = array();
        foreach ($this->iterator as $path => $SplFileInfo) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            $file = file_get_contents($path);
            if (preg_match_all("{\n(abstract class|interface|class) ([a-z0-9_]+)( |\{)}i", $file, $matches)) {

                $classes = $matches[2];

                if (count($classes) > 1) {
                    $countFiles++;
                    foreach ($classes as $class) {
                        $countMulti++;
                        $files[$path][] = $class;
                    }
                }

            }

        }

        if (count($files)) {

            $this->say("  * FAIL ({$countFiles}:{$countMulti})", Worker::MESSAGE_DEBUG);

            foreach ($files as $file) {
                $this->say("    - {$file}", Worker::MESSAGE_DEBUG);
            }

        } else {

            $this->say('  * OKAY', Worker::MESSAGE_DEBUG);

        }

        return $this;

    }

    /**
     * @return  StandardsCheck
     */
    public function findMisMatchClassFileNames()
    {
        $this->say('Mis-Matching Class File Names', Worker::MESSAGE_DEBUG);

        $countFiles = 0;
        $files = array();

        foreach ($this->iterator as $path => $SplFileInfo) {

            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            $file = file_get_contents($path);
            $filename = $SplFileInfo->getBasename('.php');

            if (preg_match("{\n(abstract class|interface|class) ([a-z0-9_]+)( |\{)}i", $file, $match)) {

                if ($filename !== $match[2] || (ctype_upper($match[2]) && !ctype_upper($filename))) {

                    $countFiles++;
                    $files[$match[2]] = str_replace(LIB, '', $path);

                }

            }

        }

        if (count($files)) {

            $this->say("  * FAIL ($countFiles)", Worker::MESSAGE_DEBUG);

            foreach ($files as $file) {
                $this->say("    - $file", Worker::MESSAGE_DEBUG);
            }

        } else {

            $this->say('  * OKAY', Worker::MESSAGE_DEBUG);

        }

        return $this;

    }

    /**
     * @return  StandardsCheck
     */
    public function findUpperClass()
    {
        $this->say('UpperClass', Worker::MESSAGE_DEBUG);

        $countFiles = 0;
        $countMulti = 0;
        $files = array();

        foreach ($this->iterator as $path => $SplFileInfo) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            $file = file_get_contents($path);
            if (preg_match_all("{\n(class|abstract class|interface) ([A-Z0-9_]+) }", $file, $matches)) {

                $classes = $matches[2];

                if (count($classes) > 1) {
                    $countFiles++;
                    foreach ($classes as $class) {
                        $countMulti++;
                        $files[$path][] = $class;
                    }
                }

            }

        }

        if (count($files)) {

            $this->say("  * FAIL ({$countFiles}:{$countMulti})");

            foreach ($files as $file) {

                $this->say($file, Worker::MESSAGE_DEBUG);

            }

        } else {

            $this->say('  * OKAY', Worker::MESSAGE_DEBUG);

        }

        return $this;


    }

} 