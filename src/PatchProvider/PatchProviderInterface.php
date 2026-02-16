<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

interface PatchProviderInterface
{
    /**
     * Check if this provider supports the given patch directory
     *
     * @param string $patchDir Path to the patch directory
     * @return bool
     */
    public function supports(string $patchDir): bool;

    /**
     * Execute the patch in the given repository directory
     *
     * @param string $patchDir Path to the patch directory
     * @param string $repoDir Path to the cloned repository
     * @return string Output from the patch execution
     */
    public function execute(string $patchDir, string $repoDir): string;
}
