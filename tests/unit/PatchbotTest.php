<?php

use Pixelbrackets\Patchbot\RoboFile;
use PHPUnit\Framework\TestCase;

class PatchbotTest extends TestCase
{
    protected function setUp(): void
    {
        \Robo\Robo::unsetContainer();
        $container = \Robo\Robo::createDefaultContainer();
        \Robo\Robo::setContainer($container);
    }

    public function testPatchRequiresRepositoryUrl()
    {
        $patchbot = new RoboFile();
        $expectedOutput = null;

        $parameters = [];
        $this->assertSame($expectedOutput, $patchbot->patch($parameters));
        $parameters = ['repository-url' => ''];
        $this->assertSame($expectedOutput, $patchbot->patch($parameters));
    }
}
