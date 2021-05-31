<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;

require __DIR__ . '/src/CommandTesterTrait.php';

class PatchbotCommandsTest extends TestCase
{
    use CommandTesterTrait;

    /** @var string[] */
    protected $commandClass;

    /**
     * Prepare CLI setup
     */
    protected function setUp(): void
    {
        $this->commandClass = [ \Pixelbrackets\Patchbot\RoboFile::class ];
        $this->setupCommandTester('TestFixtureApp', '1.0.1');
    }

    /**
     * Data provider for testExampleCommands.
     */
    public function generalCommandsProvider()
    {
        return [

            [
                'patch',
                0,
                'list',
            ],
            [
                '--repository-url',
                0,
                'patch', '--help'
            ],
            [
                'Missing arguments',
                1,
                'patch',
            ],
            [
                'Missing arguments',
                1,
                'merge',
            ],
            [
                'Missing arguments',
                1,
                'create',
            ]
        ];
    }

    /**
     * @dataProvider generalCommandsProvider
     */
    public function testGeneralCommands($expectedOutput, $expectedStatus, $CliArguments)
    {
        // Create Robo arguments and execute a runner instance
        $argv = $this->argv(func_get_args());
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        // Confirm that our output and status code match expectations
        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }
}
