<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

class PythonProvider implements PatchProviderInterface
{
    public function supports(string $patchDir): bool
    {
        return file_exists($patchDir . '/patch.py');
    }

    public function execute(string $patchDir, string $repoDir): string
    {
        return (string) shell_exec('python3 ' . escapeshellcmd($patchDir . '/patch.py'));
    }
}
