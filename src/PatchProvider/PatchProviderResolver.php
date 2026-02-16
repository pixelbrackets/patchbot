<?php

namespace Pixelbrackets\Patchbot\PatchProvider;

class PatchProviderResolver
{
    /** @var PatchProviderInterface[] */
    protected array $providers;

    public function __construct()
    {
        $this->providers = [
            new PhpProvider(),
            new ShellProvider(),
            new GitPatchProvider(),
            new PythonProvider(),
        ];
    }

    /**
     * Resolve the patch provider for the given patch directory
     *
     * @param string $patchDir Path to the patch directory
     * @return PatchProviderInterface
     * @throws \RuntimeException If no provider matches or multiple providers match
     */
    public function resolve(string $patchDir): PatchProviderInterface
    {
        $matches = [];
        $matchedFiles = [];

        foreach ($this->providers as $provider) {
            if ($provider->supports($patchDir)) {
                $matches[] = $provider;
                $matchedFiles[] = $this->getPatchFileName($provider);
            }
        }

        if (count($matches) === 0) {
            throw new \RuntimeException(
                'No patch file found in ' . $patchDir
                . ' (supported: patch.php, patch.sh, patch.diff, patch.py)'
            );
        }

        if (count($matches) > 1) {
            throw new \RuntimeException(
                'Multiple patch files found in ' . $patchDir
                . ': ' . implode(', ', $matchedFiles)
                . ' - only one patch file per directory is allowed'
            );
        }

        return $matches[0];
    }

    /**
     * Get the patch file name for a provider
     *
     * @param PatchProviderInterface $provider
     * @return string
     */
    protected function getPatchFileName(PatchProviderInterface $provider): string
    {
        return match (true) {
            $provider instanceof PhpProvider => 'patch.php',
            $provider instanceof ShellProvider => 'patch.sh',
            $provider instanceof GitPatchProvider => 'patch.diff',
            $provider instanceof PythonProvider => 'patch.py',
            default => '(unknown)',
        };
    }
}
