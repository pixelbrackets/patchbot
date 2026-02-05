<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;

class PatchbotTest extends TestCase
{
    protected function setUp(): void
    {
        \Robo\Robo::unsetContainer();
        $container = \Robo\Robo::createContainer();
        \Robo\Robo::setContainer($container);
    }

    public function testPatchRequiresRepositoryUrl(): void
    {
        $patchbot = new RoboFile();
        $expectedOutput = 1; // exit code 1 = error

        $parameters = [];
        $this->assertSame($expectedOutput, $patchbot->patch($parameters));
        $parameters = ['repository-url' => ''];
        $this->assertSame($expectedOutput, $patchbot->patch($parameters));
    }
}
