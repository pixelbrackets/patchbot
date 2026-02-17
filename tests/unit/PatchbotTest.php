<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PatchbotTest extends TestCase
{
    public function testRoboFileClassExists(): void
    {
        $this->assertTrue(class_exists(RoboFile::class));
    }

    public function testRoboFileHasPatchMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'patch'));
    }

    public function testRoboFileHasMergeMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'merge'));
    }

    public function testRoboFileHasCreateMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'create'));
    }

    public function testRoboFileHasBatchMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'batch'));
    }

    public function testRoboFileHasDiscoverMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'discover'));
    }

    public function testRoboFileHasPatchManyMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'patchMany'));
    }

    public function testRoboFileHasMergeManyMethod(): void
    {
        $this->assertTrue(method_exists(RoboFile::class, 'mergeMany'));
    }

    public function testGetCacheDirectoryFromEnv(): void
    {
        putenv('PATCHBOT_CACHE_DIR=/custom/cache/dir');
        $roboFile = new RoboFile();
        $method = new \ReflectionMethod($roboFile, 'getCacheDirectory');

        $result = $method->invoke($roboFile);

        $this->assertEquals('/custom/cache/dir', $result);
        putenv('PATCHBOT_CACHE_DIR');
    }

    public static function parseRepositoryUrlProvider(): array
    {
        return [
            'SSH' => ['git@gitlab.com:user/repo.git', 'gitlab.com', 'user/repo'],
            'SSH without .git' => ['git@gitlab.com:user/repo', 'gitlab.com', 'user/repo'],
            'HTTPS' => ['https://gitlab.com/user/repo.git', 'gitlab.com', 'user/repo'],
            'HTTPS without .git' => ['https://gitlab.com/user/repo', 'gitlab.com', 'user/repo'],
            'GitHub SSH' => ['git@github.com:user/repo.git', 'github.com', 'user/repo'],
            'Custom host' => ['git@git.example.com:team/project.git', 'git.example.com', 'team/project'],
            'file URL' => ['file:///tmp/repo.git', 'local', '/tmp/repo'],
            'Nested namespace' => ['git@gitlab.com:org/group/subgroup/repo.git', 'gitlab.com', 'org/group/subgroup/repo'],
            'HTTPS nested namespace' => ['https://gitlab.com/org/group/subgroup/repo.git', 'gitlab.com', 'org/group/subgroup/repo'],
        ];
    }

    #[DataProvider('parseRepositoryUrlProvider')]
    public function testParseRepositoryUrl(string $url, string $expectedHostname, string $expectedPath): void
    {
        $roboFile = new RoboFile();
        $method = new \ReflectionMethod($roboFile, 'parseRepositoryUrl');

        $result = $method->invoke($roboFile, $url);

        $this->assertEquals($expectedHostname, $result['hostname']);
        $this->assertEquals($expectedPath, $result['path']);
    }
}
