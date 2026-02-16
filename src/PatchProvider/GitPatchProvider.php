<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

class GitPatchProvider implements PatchProviderInterface
{
    public function supports(string $patchDir): bool
    {
        return file_exists($patchDir . '/patch.diff');
    }

    public function execute(string $patchDir, string $repoDir): string
    {
        return (string) shell_exec('git apply ' . escapeshellcmd($patchDir . '/patch.diff') . ' 2>&1');
    }
}
