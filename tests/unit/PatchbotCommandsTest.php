<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require __DIR__ . '/src/CommandTesterTrait.php';

class PatchbotCommandsTest extends TestCase
{
    use CommandTesterTrait;

    /** @var string[] */
    protected array $commandClass;

    protected static string $bareRepository = '';
    protected static string $projectRoot = '';
    protected static string $starterTemplatePatchDirectory = '';

    /**
     * Set up a bare local Git repository
     *
     * Additional method since data providers get executed before
     * setupBeforeClass, see
     * https://github.com/sebastianbergmann/phpunit/issues/836
     */
    public static function loadFixtures(): void
    {
        if (empty(self::$projectRoot)) {
            self::$projectRoot = dirname(__DIR__, 2);
        }
        if (empty(self::$bareRepository)) {
            self::$bareRepository = sys_get_temp_dir() . '/' . 'patchbot-source-repository-' . uniqid('', true) . '.git';
            $tmpDirectory = sys_get_temp_dir() . '/' . 'patchbot-source-repository-clone-' . uniqid('', true) . '/';
            exec('git init --bare ' . self::$bareRepository);
            exec('git clone ' . self::$bareRepository . ' ' . $tmpDirectory . ' 2> /dev/null');
            chdir($tmpDirectory);
            exec('git config user.email "patchbot@example.com" && git config user.name "Patchbot"');
            exec('git checkout --orphan main 2> /dev/null');
            file_put_contents($tmpDirectory . 'README.md', '# ACME Project' . PHP_EOL . 'Hello World' . PHP_EOL . PHP_EOL);
            exec('git add -A');
            exec('git commit -a -m "Add README"');
            exec('git push origin main 2> /dev/null');
        }
        if (empty(self::$starterTemplatePatchDirectory)) {
            self::$starterTemplatePatchDirectory = sys_get_temp_dir() . '/patchbot-starter-template-patch-' . uniqid('', true);
            $templateDir = self::$projectRoot . '/resources/templates/';
            mkdir(self::$starterTemplatePatchDirectory . '/starter-template-patch', 0777, true);
            copy($templateDir . 'patch.php', self::$starterTemplatePatchDirectory . '/starter-template-patch/patch.php');
            copy($templateDir . 'commit-message.txt', self::$starterTemplatePatchDirectory . '/starter-template-patch/commit-message.txt');
        }
    }

    public static function setUpBeforeClass(): void
    {
        self::loadFixtures();
    }

    public static function tearDownAfterClass(): void
    {
        // Ensure Robo container is available for temp directory cleanup
        if (!\Robo\Robo::hasContainer()) {
            $container = \Robo\Robo::createContainer();
            \Robo\Robo::setContainer($container);
        }
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
     * Data provider for testGeneralCommands.
     */
    public static function generalCommandsProvider(): array
    {
        return [
            [
                'patch',
                0,
                'list',
            ],
            [
                '<patchName>',
                0,
                'patch', '--help'
            ],
            [
                'Missing repository URL',
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
                'create', '--no-interaction',
            ],
            [
                'Not enough arguments',
                1,
                'batch',
            ]
        ];
    }

    #[DataProvider('generalCommandsProvider')]
    public function testGeneralCommands(string $expectedOutput, int $expectedStatus, string ...$cliArguments): void
    {
        // Create Robo arguments and execute a runner instance
        $argv = array_merge([$this->appName], $cliArguments);
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        // Confirm that our output and status code match expectations
        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }

    /**
     * Data provider for testPatchCommandWarning.
     */
    public static function patchCommandWarningProvider(): array
    {
        self::loadFixtures();

        return [
            [
                'Cloning failed',
                1,
                'patch', 'template', 'file:///not-existing-repository-' . microtime()
            ],
            [
                'Branch creation failed',
                1,
                'patch', 'template', 'file://' . self::$bareRepository, '--source-branch=branch-does-not-exist'
            ],
            [
                'nothing to change',
                0,
                'patch', 'starter-template-patch', 'file://' . self::$bareRepository,
                '--patch-source-directory=' . self::$starterTemplatePatchDirectory
            ]
        ];
    }

    #[DataProvider('patchCommandWarningProvider')]
    public function testPatchCommandWarning(string $expectedOutput, int $expectedStatus, string ...$cliArguments): void
    {
        // Create Robo arguments and execute a runner instance
        $argv = array_merge([$this->appName], $cliArguments);
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }
}
