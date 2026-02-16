<?php

use Pixelbrackets\Patchbot\PatchProvider\PhpProvider;
use Pixelbrackets\Patchbot\PatchProvider\ShellProvider;
use Pixelbrackets\Patchbot\PatchProvider\GitPatchProvider;
use Pixelbrackets\Patchbot\PatchProvider\PythonProvider;
use Pixelbrackets\Patchbot\PatchProvider\PatchProviderResolver;
use PHPUnit\Framework\TestCase;

class PatchProviderTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    protected static string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        self::$tempDir = sys_get_temp_dir() . '/patchbot-provider-test-' . uniqid('', true);
        mkdir(self::$tempDir);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up temp directories
        $dirs = glob(self::$tempDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            array_map('unlink', glob($dir . '/*'));
            rmdir($dir);
        }
        rmdir(self::$tempDir);
    }

    protected function createPatchDir(string $name, array $files): string
    {
        $dir = self::$tempDir . '/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        foreach ($files as $file => $content) {
            file_put_contents($dir . '/' . $file, $content);
        }
        return $dir;
    }

    // --- PhpProvider ---

    public function testPhpProviderSupportsMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('php-patch', ['patch.php' => '<?php echo "ok";']);
        $provider = new PhpProvider();
        $this->assertTrue($provider->supports($dir));
    }

    public function testPhpProviderRejectsNonMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('shell-only', ['patch.sh' => 'echo ok']);
        $provider = new PhpProvider();
        $this->assertFalse($provider->supports($dir));
    }

    public function testPhpProviderExecute(): void
    {
        $dir = $this->createPatchDir('php-exec', ['patch.php' => '<?php echo "hello from php";']);
        $provider = new PhpProvider();
        $output = $provider->execute($dir, sys_get_temp_dir());
        $this->assertStringContainsString('hello from php', $output);
    }

    // --- ShellProvider ---

    public function testShellProviderSupportsMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('shell-patch', ['patch.sh' => 'echo ok']);
        $provider = new ShellProvider();
        $this->assertTrue($provider->supports($dir));
    }

    public function testShellProviderRejectsNonMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('php-only', ['patch.php' => '<?php']);
        $provider = new ShellProvider();
        $this->assertFalse($provider->supports($dir));
    }

    public function testShellProviderExecute(): void
    {
        $dir = $this->createPatchDir('shell-exec', ['patch.sh' => 'echo "hello from shell"']);
        $provider = new ShellProvider();
        $output = $provider->execute($dir, sys_get_temp_dir());
        $this->assertStringContainsString('hello from shell', $output);
    }

    // --- GitPatchProvider ---

    public function testGitPatchProviderSupportsMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('diff-patch', ['patch.diff' => '']);
        $provider = new GitPatchProvider();
        $this->assertTrue($provider->supports($dir));
    }

    public function testGitPatchProviderRejectsNonMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('no-diff', ['patch.php' => '<?php']);
        $provider = new GitPatchProvider();
        $this->assertFalse($provider->supports($dir));
    }

    // --- PythonProvider ---

    public function testPythonProviderSupportsMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('python-patch', ['patch.py' => 'print("ok")']);
        $provider = new PythonProvider();
        $this->assertTrue($provider->supports($dir));
    }

    public function testPythonProviderRejectsNonMatchingDirectory(): void
    {
        $dir = $this->createPatchDir('no-python', ['patch.sh' => 'echo ok']);
        $provider = new PythonProvider();
        $this->assertFalse($provider->supports($dir));
    }

    // --- Fixture-based execute tests ---

    public function testPhpProviderExecuteFixture(): void
    {
        $patchDir = self::FIXTURES_DIR . '/php-patch';
        $workDir = sys_get_temp_dir() . '/patchbot-php-exec-' . uniqid('', true);
        mkdir($workDir);
        $originalDir = getcwd();
        chdir($workDir);

        $provider = new PhpProvider();
        $provider->execute($patchDir, $workDir);

        $this->assertFileExists($workDir . '/example.txt');
        $this->assertSame('example content', file_get_contents($workDir . '/example.txt'));

        chdir($originalDir);
        unlink($workDir . '/example.txt');
        rmdir($workDir);
    }

    public function testShellProviderExecuteFixture(): void
    {
        $patchDir = self::FIXTURES_DIR . '/shell-patch';
        $workDir = sys_get_temp_dir() . '/patchbot-shell-exec-' . uniqid('', true);
        mkdir($workDir);
        $originalDir = getcwd();
        chdir($workDir);

        $provider = new ShellProvider();
        $provider->execute($patchDir, $workDir);

        $this->assertFileExists($workDir . '/example.txt');
        $this->assertStringContainsString('example content', file_get_contents($workDir . '/example.txt'));

        chdir($originalDir);
        unlink($workDir . '/example.txt');
        rmdir($workDir);
    }

    public function testPythonProviderExecuteFixture(): void
    {
        $patchDir = self::FIXTURES_DIR . '/python-patch';
        $workDir = sys_get_temp_dir() . '/patchbot-python-exec-' . uniqid('', true);
        mkdir($workDir);
        $originalDir = getcwd();
        chdir($workDir);

        $provider = new PythonProvider();
        $provider->execute($patchDir, $workDir);

        $this->assertFileExists($workDir . '/example.txt');
        $this->assertSame('example content', file_get_contents($workDir . '/example.txt'));

        chdir($originalDir);
        unlink($workDir . '/example.txt');
        rmdir($workDir);
    }

    public function testGitPatchProviderExecuteFixture(): void
    {
        $patchDir = self::FIXTURES_DIR . '/diff-patch';
        $workDir = sys_get_temp_dir() . '/patchbot-diff-exec-' . uniqid('', true);
        mkdir($workDir);
        $originalDir = getcwd();
        chdir($workDir);

        // Initialize a git repo (required for git apply)
        shell_exec('git init 2>&1');

        $provider = new GitPatchProvider();
        $provider->execute($patchDir, $workDir);

        $this->assertFileExists($workDir . '/example.txt');
        $this->assertStringContainsString('example content', file_get_contents($workDir . '/example.txt'));

        chdir($originalDir);
        shell_exec('rm -rf ' . escapeshellarg($workDir));
    }

    // --- PatchProviderResolver ---

    public function testResolverReturnPhpProvider(): void
    {
        $dir = $this->createPatchDir('resolve-php', ['patch.php' => '<?php']);
        $resolver = new PatchProviderResolver();
        $provider = $resolver->resolve($dir);
        $this->assertInstanceOf(PhpProvider::class, $provider);
    }

    public function testResolverReturnShellProvider(): void
    {
        $dir = $this->createPatchDir('resolve-shell', ['patch.sh' => 'echo ok']);
        $resolver = new PatchProviderResolver();
        $provider = $resolver->resolve($dir);
        $this->assertInstanceOf(ShellProvider::class, $provider);
    }

    public function testResolverReturnGitPatchProvider(): void
    {
        $dir = $this->createPatchDir('resolve-diff', ['patch.diff' => '']);
        $resolver = new PatchProviderResolver();
        $provider = $resolver->resolve($dir);
        $this->assertInstanceOf(GitPatchProvider::class, $provider);
    }

    public function testResolverReturnPythonProvider(): void
    {
        $dir = $this->createPatchDir('resolve-python', ['patch.py' => 'print("ok")']);
        $resolver = new PatchProviderResolver();
        $provider = $resolver->resolve($dir);
        $this->assertInstanceOf(PythonProvider::class, $provider);
    }

    public function testResolverThrowsOnNoMatch(): void
    {
        $dir = $this->createPatchDir('resolve-empty', ['readme.txt' => 'nothing here']);
        $resolver = new PatchProviderResolver();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No patch file found');
        $resolver->resolve($dir);
    }

    public function testResolverThrowsOnMultipleMatches(): void
    {
        $dir = $this->createPatchDir('resolve-multi', [
            'patch.php' => '<?php',
            'patch.sh' => 'echo ok',
        ]);
        $resolver = new PatchProviderResolver();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multiple patch files found');
        $resolver->resolve($dir);
    }
}
