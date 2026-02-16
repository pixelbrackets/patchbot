<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

class ShellProvider implements PatchProviderInterface
{
    public function supports(string $patchDir): bool
    {
        return file_exists($patchDir . '/patch.sh');
    }

    public function execute(string $patchDir, string $repoDir): string
    {
        return (string) shell_exec('bash ' . escapeshellcmd($patchDir . '/patch.sh'));
    }
}
