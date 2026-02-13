<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;

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
}
