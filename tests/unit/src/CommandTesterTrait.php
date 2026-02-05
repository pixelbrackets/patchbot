<?php

use Symfony\Component\Console\Output\BufferedOutput;

trait CommandTesterTrait
{
    protected string $appName;

    protected string $appVersion;

    /**
     * Instantiate a new runner
     */
    public function setupCommandTester(string $appName, string $appVersion): void
    {
        $this->appName = $appName;
        $this->appVersion = $appVersion;
    }

    /**
     * Simulated Robo task runner execution
     *
     * @param string[] $argv
     * @param string[] $commandClass
     * @return array{0: string, 1: int}
     */
    protected function execute(array $argv, array $commandClass): array
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
