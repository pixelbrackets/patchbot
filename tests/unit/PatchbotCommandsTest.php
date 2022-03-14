<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;

require __DIR__ . '/src/CommandTesterTrait.php';

class PatchbotCommandsTest extends TestCase
{
    use CommandTesterTrait;

    /** @var string[] */
    protected $commandClass;

    protected static $bareRepository = '';

    /**
     * Set up a bare local Git repository
     *
     * Additional method since data providers get executed before
     * setupBeforeClass, see
     * https://github.com/sebastianbergmann/phpunit/issues/836
     */
    public static function loadFixtures(): void
    {
        if (empty(self::$bareRepository)) {
            self::$bareRepository = sys_get_temp_dir() . '/' . 'patchbot-source-repository-' . uniqid('', true) . '.git';
            $tmpDirectory = sys_get_temp_dir() . '/' . 'patchbot-source-repository-clone-' . uniqid('', true) . '/';
            exec('git init --bare ' . self::$bareRepository);
            exec('git clone ' . self::$bareRepository . ' ' . $tmpDirectory . ' 2> /dev/null');
            chdir($tmpDirectory);
            exec('git config --global user.email "patchbot@example.com" && git config --global user.name "Patchbot"');
            exec('git checkout --orphan master 2> /dev/null');
            file_put_contents($tmpDirectory . 'README.md', '# ACME Project' . PHP_EOL . 'Hello World' . PHP_EOL . PHP_EOL);
            exec('git add -A');
            exec('git commit -a -m "Add README"');
            exec('git push origin master 2> /dev/null');
        }
    }

    public static function setUpBeforeClass(): void
    {
        self::loadFixtures();
    }

    public static function tearDownAfterClass(): void
    {
        //rmdir(self::$bareRepository);
    }

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
    public function generalCommandsProvider(): array
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
            ],
            [
                'Not enough arguments ',
                1,
                'batch',
            ]
        ];
    }

    /**
     * @dataProvider generalCommandsProvider
     */
    public function testGeneralCommands($expectedOutput, $expectedStatus, $CliArguments): void
    {
        // Create Robo arguments and execute a runner instance
        $argv = $this->argv(func_get_args());
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        // Confirm that our output and status code match expectations
        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }

    /**
     * Data provider for testPatchCommandWarning.
     */
    public function patchCommandWarningProvider(): array
    {
        self::loadFixtures();

        return [
            [
                'Cloning failed',
                1,
                'patch', '--repository-url=file:///not-existing-repository-' . microtime()
            ],
            [
                'Branch creation failed',
                1,
                'patch', '--source-branch=branch-does-not-exist', '--repository-url=file://' . self::$bareRepository
            ],
            [
                'nothing to change',
                0,
                'patch', '--repository-url=file://' . self::$bareRepository
            ]
        ];
    }

    /**
     * @dataProvider patchCommandWarningProvider
     */
    public function testPatchCommandWarning($expectedOutput, $expectedStatus, $CliArguments): void
    {
        // Create Robo arguments and execute a runner instance
        $argv = $this->argv(func_get_args());
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }
}
