<?php

#namespace Pixelbrackets\Patchbot;

use Symfony\Component\Console\Output\BufferedOutput;

trait CommandTesterTrait
{
    /** @var string */
    protected $appName;

    /** @var string */
    protected $appVersion;

    /**
     * Instantiate a new runner
     */
    public function setupCommandTester($appName, $appVersion)
    {
        $this->appName = $appName;
        $this->appVersion = $appVersion;
    }

    /**
     * Helper method to set up the $argv array for Robo:
     * <app name> <command> <command options>
     *
     * @param array $functionParameters All test method arguments
     * @param int $leadingParameterCount The number of method argumnents
     *   to ignore in this helper - 2 by default
     *   (first argument = expected content, second argument = expected
     *   status code, all following arguments = argv).
     */
    protected function argv($functionParameters, $leadingParameterCount = 2)
    {
        $argv = $functionParameters;
        $argv = array_slice($argv, $leadingParameterCount);
        array_unshift($argv, $this->appName);

        return $argv;
    }

    /**
     * Simulated Robo task runner execution
     */
    protected function execute($argv, $commandClass)
    {
        // Buffer CLI output for tests
        $output = new BufferedOutput();

        // We can only call `Runner::execute()` only once, afterwards we need
        // to tear it down again
        $runner = new \Robo\Runner($commandClass);
        $statusCode = $runner->execute($argv, $this->appName, $this->appVersion, $output);
        \Robo\Robo::unsetContainer();

        // Return output and status code
        return [trim($output->fetch()), $statusCode];
    }
}
