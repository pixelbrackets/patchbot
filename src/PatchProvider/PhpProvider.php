<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

class PhpProvider implements PatchProviderInterface
{
    public function supports(string $patchDir): bool
    {
        return file_exists($patchDir . '/patch.php');
    }

    public function execute(string $patchDir, string $repoDir): string
    {
        return (string) shell_exec('php ' . escapeshellcmd($patchDir . '/patch.php'));
    }
}
