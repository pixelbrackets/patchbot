<?php

namespace Pixelbrackets\Patchbot;

use Cocur\Slugify\Slugify;
use Pixelbrackets\Patchbot\Discovery\GitLabDiscovery;
use Pixelbrackets\Patchbot\PatchProvider\PatchProviderResolver;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Exception\TaskException;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Patchbot tasks (based on Robo)
 *
 *   ./vendor/bin/patchbot patch
 *
 */
class RoboFile extends \Robo\Tasks
{
    /**
     * Apply changes, commit, push
     *
     * @param string $patchName Name of the patch directory (positional arg)
     * @param string $repositoryUrl URI of Git repository (positional arg)
     * @param array $options
     * @option $patch-name Name of the patch directory (alternative to positional arg)
     * @option $repository-url URI of Git repository (alternative to positional arg)
     * @option $working-directory Working directory to check out repositories
     * @option $patch-source-directory Source directory for all collected patches
     * @option $source-branch Name of the branch to create a new branch upon
     * @option $branch-name Name of the feature branch to be created
     * @option $halt-before-commit Pause before changes are committed, asks to continue
     * @option $dry-run Show what would be done without executing
     * @option $create-mr Create GitLab merge request after pushing
     * @return int exit code
     * @throws TaskException
     */
    public function patch(
        string $patchName = '',
        string $repositoryUrl = '',
        array $options = [
            'patch-name|p' => '',
            'repository-url|g' => '',
            'working-directory|d' => null,
            'patch-source-directory|s' => null,
            'source-branch' => 'main',
            'branch-name' => null,
            'halt-before-commit' => false,
            'dry-run' => false,
            'create-mr' => false,
        ]
    ): int {
        // Support both positional args and options (backwards compatibility)
        $options['patch-name'] = $patchName ?: ($options['patch-name'] ?: 'template');
        $options['repository-url'] = $repositoryUrl ?: $options['repository-url'];

        if (empty($options['repository-url'])) {
            $this->io()->error('Missing repository URL');
            return 1;
        }

        $options['patch-source-directory'] = ($options['patch-source-directory'] ?? getcwd() . '/patches') . '/';
        $options['working-directory'] = $options['working-directory'] ?? $this->getTemporaryDirectory();
        /** @noinspection NonSecureUniqidUsageInspection */
        $options['branch-name'] = $options['branch-name'] ?? date('Ymd') . '_' . 'patchbot_' . uniqid();

        // Print summary
        $this->io()->section($options['dry-run'] ? 'Patch (Dry Run)' : 'Patch');
        $this->io()->listing([
            'Patch: ' . $options['patch-name'],
            'Branch: ' . $options['branch-name'],
            'Repository: ' . pathinfo($options['repository-url'], PATHINFO_FILENAME) . ' (' . $options['repository-url'] . ')'
        ]);

        if ($options['dry-run']) {
            $this->io()->text('[DRY-RUN] Would clone repository and create branch');
            $this->io()->text('[DRY-RUN] Would run patch script in: ' . $options['patch-source-directory'] . $options['patch-name'] . '/');
            $this->io()->text('[DRY-RUN] Would commit and push changes');
            if ($options['create-mr']) {
                $this->io()->text('[DRY-RUN] Would create merge request');
            }
            return 0;
        }

        try {
            $patchApplied = $this->runPatch($options);
        } catch (Exception | TaskException $e) {
            $this->io()->error('An error occured');
            throw new TaskException($this, 'Something went wrong' . $e->getMessage());
        }

        if ($patchApplied === false) {
            $this->io()->warning('Patch not applied (nothing to change)');
            return 0;
        }

        $this->io()->success('Patch applied');

        // Create merge request if requested
        if ($options['create-mr']) {
            $commitMessage = file_get_contents($options['patch-source-directory'] . $options['patch-name'] . '/commit-message.txt');
            $mrUrl = $this->createMergeRequest(
                $options['repository-url'],
                $options['branch-name'],
                $options['source-branch'],
                $commitMessage
            );
            if ($mrUrl) {
                $this->io()->success('Merge request created: ' . $mrUrl);
            }
        } else {
            // Suggest next steps
            $this->say('Hint: Run `./vendor/bin/patchbot merge '
                . $options['branch-name'] . ' <target-branch> '
                . $options['repository-url'] . '` to merge the feature branch');
        }

        return 0;
    }

    /**
     * Merge one branch into another, push
     *
     * @param string $sourceBranch Source branch name (positional arg)
     * @param string $targetBranch Target branch name (positional arg)
     * @param string $repositoryUrl URI of Git repository (positional arg)
     * @param array $options
     * @option $source Source branch name (alternative to positional arg)
     * @option $target Target branch name (alternative to positional arg)
     * @option $repository-url URI of Git repository (alternative to positional arg)
     * @option $working-directory Working directory to check out repositories
     * @option $dry-run Show what would be done without executing
     * @return int exit code
     * @throws TaskException
     */
    public function merge(
        string $sourceBranch = '',
        string $targetBranch = '',
        string $repositoryUrl = '',
        array $options = [
            'source|s' => '',
            'target|t' => '',
            'repository-url|g' => '',
            'working-directory|d' => null,
            'dry-run' => false,
        ]
    ): int {
        // Support both positional args and options (backwards compatibility)
        $options['source'] = $sourceBranch ?: $options['source'];
        $options['target'] = $targetBranch ?: $options['target'];
        $options['repository-url'] = $repositoryUrl ?: $options['repository-url'];

        if (
            empty($options['repository-url']) ||
            empty($options['source']) ||
            empty($options['target'])
        ) {
            $this->io()->error('Missing arguments');
            return 1;
        }

        $options['working-directory'] = $options['working-directory'] ?? $this->getTemporaryDirectory();

        // Print summary
        $this->io()->section($options['dry-run'] ? 'Merge (Dry Run)' : 'Merge');
        $this->io()->listing([
            'Source Branch: ' . $options['source'],
            'Target Branch: ' . $options['target'],
            'Repository: ' . pathinfo($options['repository-url'], PATHINFO_FILENAME) . ' (' . $options['repository-url'] . ')'
        ]);

        if ($options['dry-run']) {
            $this->io()->text('[DRY-RUN] Would clone repository');
            $this->io()->text('[DRY-RUN] Would merge ' . $options['source'] . ' into ' . $options['target']);
            $this->io()->text('[DRY-RUN] Would push changes');
            return 0;
        }

        try {
            $branchMerged = $this->runMerge($options);
        } catch (Exception | TaskException $e) {
            $this->io()->error('An error occured');
            throw new TaskException($this, 'Something went wrong' . $e->getMessage());
        }

        if ($branchMerged === false) {
            $this->io()->warning('Branch not merged (everything up-to-date)');
            return 0;
        }

        $this->io()->success('Merge done');

        return 0;
    }

    /**
     * Create a new patch
     *
     * When called without arguments, an interactive wizard guides through
     * patch name and type selection. When arguments are provided, runs
     * non-interactively (suitable for CI and scripted usage).
     *
     * @param string $patchName Name of the patch (positional arg)
     * @param array $options
     * @option $patch-name Name of the patch (alternative to positional arg)
     * @option $type Patch type: php, sh, diff, py (default: php)
     * @return int exit code
     * @throws TaskException
     */
    public function create(
        string $patchName = '',
        array $options = [
            'patch-name|p' => '',
            'type|t' => '',
        ]
    ): int {
        // Support both positional arg and option (backwards compatibility)
        $patchName = $patchName ?: $options['patch-name'];

        // Interactive wizard: prompt for patch name when missing
        if (empty($patchName) && !$this->io()->input()->isInteractive()) {
            $this->io()->error('Missing arguments');
            return 1;
        }
        if (empty($patchName)) {
            $patchName = $this->ask('Patch name (e.g. "Add CHANGELOG file")');
            if (empty($patchName)) {
                $this->io()->error('Patch name is required');
                return 1;
            }
        }

        // Resolve patch type
        $patchFiles = ['php' => 'patch.php', 'sh' => 'patch.sh', 'diff' => 'patch.diff', 'py' => 'patch.py'];
        $type = $options['type'];

        if (empty($type) && $this->io()->input()->isInteractive()) {
            $question = new ChoiceQuestion('Patch type', array_keys($patchFiles), 0);
            $type = $this->io()->askQuestion($question);
        }
        if (empty($type)) {
            $type = 'php';
        }

        if (!isset($patchFiles[$type])) {
            $this->io()->error('Invalid patch type "' . $type . '". Supported: php, sh, diff, py');
            return 1;
        }

        $patchFile = $patchFiles[$type];
        $slugifiedName = (new Slugify())->slugify($patchName);
        $patchDirectory = getcwd() . '/patches/' . $slugifiedName;

        // Print summary
        $this->io()->section('Create');
        $this->io()->listing([
            'Patch: ' . $patchName,
            'Type: ' . $patchFile,
        ]);

        $this->say('Create patch ' . $slugifiedName);
        if (is_dir($patchDirectory)) {
            $this->io()->error('Patch directory »' . $patchDirectory . '« already exists');
            return 1;
        }

        $templateDirectory = __DIR__ . '/../resources/templates/';
        $this->taskFilesystemStack()
            ->mkdir($patchDirectory)
            ->copy($templateDirectory . 'commit-message.txt', $patchDirectory . '/commit-message.txt')
            ->copy($templateDirectory . $patchFile, $patchDirectory . '/' . $patchFile)
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

        $this->io()->success('Patch directory created: ' . $patchDirectory);
        $this->io()->text('Next steps:');
        $this->io()->listing([
            'Edit ' . $patchFile . ' & commit-message.txt in ' . $patchDirectory,
            'Run ./vendor/bin/patchbot patch ' . $slugifiedName . ' <repository-url>',
        ]);

        return 0;
    }

    /**
     * Import a patch from a URL (Gist, Git repository, or subdirectory)
     *
     * @param string $url URL of the Git repository or Gist (positional arg)
     * @param string $patchName Name for the imported patch (positional arg, optional)
     * @param array $options
     * @option $url URL of the Git repository or Gist (alternative to positional arg)
     * @option $patch-name Name for the imported patch (alternative to positional arg)
     * @option $path Subdirectory within the repository to import
     * @return int exit code
     */
    public function import(
        string $url = '',
        string $patchName = '',
        array $options = [
            'url|u' => '',
            'patch-name|p' => '',
            'path' => '',
        ]
    ): int {
        $url = $url ?: $options['url'];
        $patchName = $patchName ?: $options['patch-name'];

        if (empty($url)) {
            $this->io()->error('Missing URL');
            return 1;
        }

        // Clone to temp directory
        $tempDirectory = $this->getTemporaryDirectory();
        $temporaryImportName = 'import-source';

        $this->say('Clone ' . $url);
        $result = $this->taskGitStack()
            ->cloneShallow($url, $temporaryImportName)
            ->dir($tempDirectory)
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            $this->io()->error('Cloning failed - check the URL and your access rights');
            return 1;
        }

        // Determine source directory
        $sourceDirectory = $tempDirectory . '/' . $temporaryImportName;
        $hasPath = !empty($options['path']);
        if ($hasPath) {
            $sourceDirectory .= '/' . trim($options['path'], '/');
        }

        if (!is_dir($sourceDirectory)) {
            $this->io()->error('Path not found in repository: ' . $options['path']);
            return 1;
        }

        // Multi-patch import: repo contains a patches/ subdirectory
        if (!$hasPath && is_dir($sourceDirectory . '/patches')) {
            $entries = array_diff(scandir($sourceDirectory . '/patches'), ['.', '..']);
            $patches = array_filter($entries, fn ($entry) => is_dir($sourceDirectory . '/patches/' . $entry));

            if (empty($patches)) {
                $this->io()->warning('No patch directories found in patches/');
                return 0;
            }

            // Print summary
            $this->io()->section('Import');
            $this->io()->listing([
                'URL: ' . $url,
                'Patches: ' . count($patches) . ' found',
            ]);

            $imported = 0;
            $skipped = 0;

            foreach ($patches as $patch) {
                $targetDirectory = getcwd() . '/patches/' . $patch;
                if (is_dir($targetDirectory)) {
                    $this->io()->text('Skipped: ' . $patch . ' (already exists)');
                    $skipped++;
                    continue;
                }

                $this->taskFilesystemStack()
                    ->mirror($sourceDirectory . '/patches/' . $patch, $targetDirectory)
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                    ->run();
                $this->io()->text('Imported: ' . $patch);
                $imported++;
            }

            $this->io()->newLine();
            if ($imported > 0) {
                $this->io()->success($imported . ' patch(es) imported');
            }
            if ($skipped > 0) {
                $this->io()->text($skipped . ' patch(es) skipped (already exist)');
            }
        } else {
            // Single patch import
            if (empty($patchName)) {
                $nameSource = $hasPath ? $options['path'] : $url;
                $patchName = (new Slugify())->slugify(basename($nameSource));
            }

            $patchDirectory = getcwd() . '/patches/' . $patchName;

            // Print summary
            $this->io()->section('Import');
            $this->io()->listing([
                'URL: ' . $url,
                'Patch: ' . $patchName,
                $hasPath ? 'Path: ' . $options['path'] : 'Path: (root)',
            ]);

            if (is_dir($patchDirectory)) {
                $this->io()->error('Patch directory already exists: ' . $patchDirectory);
                return 1;
            }

            $this->taskFilesystemStack()
                ->mirror($sourceDirectory, $patchDirectory)
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                ->run();

            // Remove .git directory if present (gist clones include it)
            $gitDir = $patchDirectory . '/.git';
            if (is_dir($gitDir)) {
                $this->taskDeleteDir($gitDir)
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                    ->run();
            }

            $this->io()->success('Patch imported: ' . $patchName);
        }

        $this->io()->text('Next steps:');
        $this->io()->listing([
            'Review and customize the imported patches in patches/',
            'Run ./vendor/bin/patchbot patch <patch-name> <repository-url>',
        ]);

        return 0;
    }


    /**
     * Export a patch for sharing
     *
     * Prints ready-to-use commands to share a patch as a GitHub Gist.
     *
     * @param string $patchName Name of the patch to export (positional arg)
     * @param array $options
     * @option $patch-name Name of the patch to export (alternative to positional arg)
     * @return int exit code
     */
    public function export(
        string $patchName = '',
        array $options = [
            'patch-name|p' => '',
        ]
    ): int {
        $patchName = $patchName ?: $options['patch-name'];

        if (empty($patchName)) {
            $this->io()->error('Missing patch name');
            return 1;
        }

        $patchDirectory = getcwd() . '/patches/' . $patchName;

        if (!is_dir($patchDirectory)) {
            $this->io()->error('Patch directory not found: ' . $patchDirectory);
            return 1;
        }

        // Warn about missing files
        $commitMessageFile = $patchDirectory . '/commit-message.txt';
        if (!is_file($commitMessageFile) || empty(trim(file_get_contents($commitMessageFile)))) {
            $this->io()->warning('Missing or empty commit-message.txt');
        }
        $resolver = new PatchProviderResolver();
        try {
            $resolver->resolve($patchDirectory);
        } catch (\RuntimeException $e) {
            $this->io()->warning($e->getMessage());
        }

        // Collect files
        $files = array_diff(scandir($patchDirectory), ['.', '..']);

        // Print summary
        $this->io()->section('Export');
        $this->io()->listing([
            'Patch: ' . $patchName,
            'Files: ' . implode(', ', $files),
        ]);

        // Create gist via GitHub CLI
        $ghAvailable = shell_exec('which gh 2>/dev/null');
        if (!$ghAvailable) {
            $this->io()->error('GitHub CLI (gh) not found. Install it from https://cli.github.com/');
            return 1;
        }

        $filesArgument = implode(' ', $files);
        $ghCommand = 'gh gist create --desc '
            . escapeshellarg('Patchbot Patch - ' . $patchName) . ' '
            . $filesArgument;

        $result = $this->taskExec($ghCommand)
            ->dir($patchDirectory)
            ->run();
        if ($result->wasSuccessful()) {
            $this->io()->success('Gist created');
        } else {
            $this->io()->error('Gist creation failed');
            return 1;
        }

        return 0;
    }

    /**
     * Discover repositories from a GitLab namespace
     *
     * @param array $options
     * @option $gitlab-namespace GitLab namespace (group path or username)
     * @option $gitlab-url GitLab instance URL
     * @option $force Overwrite repositories.yaml if it exists
     * @return int exit code
     */
    public function discover(array $options = [
        'gitlab-namespace|g' => '',
        'gitlab-url' => '',
        'force|f' => false,
    ]): int
    {
        // Get GitLab namespace: CLI option > env
        $namespace = !empty($options['gitlab-namespace']) ? $options['gitlab-namespace'] : getenv('GITLAB_NAMESPACE');
        if (empty($namespace)) {
            $this->io()->error('Missing GitLab namespace. Use --gitlab-namespace or set GITLAB_NAMESPACE in .env');
            return 1;
        }

        // Get GitLab token from environment
        $token = getenv('GITLAB_TOKEN');
        if (empty($token)) {
            $this->io()->error('Missing GITLAB_TOKEN environment variable. Create a .env file with GITLAB_TOKEN=your-token');
            return 1;
        }

        // Get GitLab URL: CLI option > env > default
        $gitlabUrl = !empty($options['gitlab-url']) ? $options['gitlab-url'] : (getenv('GITLAB_URL') ?: 'https://gitlab.com');

        $outputFile = 'repositories.json';

        // Print summary
        $this->io()->section('Discover');
        $this->io()->listing([
            'GitLab Namespace: ' . $namespace,
            'GitLab URL: ' . $gitlabUrl,
        ]);

        // Check if output file already exists
        if (file_exists($outputFile) && !$options['force']) {
            $this->io()->warning('File "' . $outputFile . '" already exists.');
            $this->io()->text([
                '',
                'Options:',
                '  - Use --force to overwrite',
                '  - Delete the file manually and run again',
                '  - Compare changes with: git diff ' . $outputFile,
                '',
            ]);
            $this->io()->error('Aborting.');
            return 1;
        }

        // Discover repositories
        try {
            $discovery = new GitLabDiscovery($token, $gitlabUrl);
            $result = $discovery->discover($namespace);
            $namespaceType = $result['type'];
            $repositories = $result['repositories'];
        } catch (\Exception $e) {
            $this->io()->error('Discovery failed: ' . $e->getMessage());
            return 1;
        }

        if (empty($repositories)) {
            $this->io()->warning('No repositories found for namespace "' . $namespace . '"');
            return 0;
        }

        $this->io()->success('Found ' . count($repositories) . ' repositories');

        // Store discovered repos as JSON
        $jsonData = [
            'generated' => date('Y-m-d H:i:s'),
            'source' => [
                'type' => $namespaceType,
                'namespace' => $namespace,
                'url' => $gitlabUrl,
            ],
            'repositories' => $repositories,
        ];

        file_put_contents($outputFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->io()->success('Written to ' . $outputFile);

        return 0;
    }

    /**
     * Apply a patch to all repositories
     *
     * @param string $patchName Name of the patch directory
     * @param array $options
     * @option $branch-name Name of the feature branch to be created
     * @option $halt-before-commit Pause before changes are committed, asks to continue
     * @option $dry-run Show what would be done without executing
     * @option $filter Filter repositories (path:pattern or topic:tag, can be used multiple times)
     * @option $create-mr Create GitLab merge request after pushing
     * @return int exit code
     * @throws TaskException
     */
    public function patchMany(
        string $patchName = 'template',
        array $options = [
            'branch-name' => null,
            'halt-before-commit' => false,
            'dry-run' => false,
            'filter' => [],
            'create-mr' => false,
        ]
    ): int {
        $repositories = $this->loadRepositories($options['filter']);
        if ($repositories === null) {
            return 1;
        }
        if (empty($repositories)) {
            return 0;
        }

        $workingDirectory = getcwd();
        $patchSourceDirectory = getcwd() . '/patches/';
        $branchName = $options['branch-name'] ?? date('Ymd') . '_patchbot_' . uniqid();
        $isDryRun = $options['dry-run'];

        if ($isDryRun) {
            $this->io()->section('Dry Run');
        }

        $results = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($repositories as $repository) {
            if ($isDryRun) {
                $this->io()->text('[DRY-RUN] Would patch: ' . $repository['path_with_namespace'] . ' (' . $repository['default_branch'] . ')');
                if ($options['create-mr']) {
                    $this->io()->text('[DRY-RUN] Would create MR');
                }
                continue;
            }
            chdir($workingDirectory);
            try {
                $result = $this->runPatch([
                    'repository-url' => $repository['clone_url_ssh'],
                    'working-directory' => $this->getTemporaryDirectory(),
                    'patch-source-directory' => $patchSourceDirectory,
                    'patch-name' => $patchName,
                    'source-branch' => $repository['default_branch'],
                    'branch-name' => $branchName,
                    'halt-before-commit' => $options['halt-before-commit'],
                ]);
                if ($result) {
                    $results['success']++;
                    $this->io()->success('Patched: ' . $repository['path_with_namespace']);

                    if ($options['create-mr']) {
                        $commitMessage = file_get_contents($patchSourceDirectory . $patchName . '/commit-message.txt');
                        $mrUrl = $this->createMergeRequest(
                            $repository['clone_url_ssh'],
                            $branchName,
                            $repository['default_branch'],
                            $commitMessage
                        );
                        if ($mrUrl) {
                            $this->io()->text('  MR: ' . $mrUrl);
                        }
                    }
                } else {
                    $results['skipped']++;
                    $this->io()->text('Skipped: ' . $repository['path_with_namespace'] . ' (no changes)');
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $this->io()->error('Failed: ' . $repository['path_with_namespace'] . ' - ' . $e->getMessage());
            }
        }

        $this->printBatchSummary($results, $isDryRun, count($repositories), 'patched');
        return $results['failed'] > 0 ? 1 : 0;
    }

    /**
     * Merge a branch into all repositories
     *
     * @param string $sourceBranch Source branch name to merge
     * @param array $options
     * @option $dry-run Show what would be done without executing
     * @option $filter Filter repositories (path:pattern or topic:tag, can be used multiple times)
     * @return int exit code
     * @throws TaskException
     */
    public function mergeMany(
        string $sourceBranch,
        array $options = [
            'dry-run' => false,
            'filter' => [],
        ]
    ): int {
        $repositories = $this->loadRepositories($options['filter']);
        if ($repositories === null) {
            return 1;
        }
        if (empty($repositories)) {
            return 0;
        }

        $workingDirectory = getcwd();
        $isDryRun = $options['dry-run'];

        if ($isDryRun) {
            $this->io()->section('Dry Run');
        }

        $results = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($repositories as $repository) {
            if ($isDryRun) {
                $this->io()->text('[DRY-RUN] Would merge: ' . $sourceBranch . ' -> ' . $repository['default_branch'] . ' in ' . $repository['path_with_namespace']);
                continue;
            }
            chdir($workingDirectory);
            try {
                $result = $this->runMerge([
                    'repository-url' => $repository['clone_url_ssh'],
                    'working-directory' => $this->getTemporaryDirectory(),
                    'source' => $sourceBranch,
                    'target' => $repository['default_branch'],
                ]);
                if ($result) {
                    $results['success']++;
                    $this->io()->success('Merged: ' . $repository['path_with_namespace']);
                } else {
                    $results['skipped']++;
                    $this->io()->text('Skipped: ' . $repository['path_with_namespace'] . ' (already up-to-date)');
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $this->io()->error('Failed: ' . $repository['path_with_namespace'] . ' - ' . $e->getMessage());
            }
        }

        $this->printBatchSummary($results, $isDryRun, count($repositories), 'merged');
        return $results['failed'] > 0 ? 1 : 0;
    }

    /**
     * Run batch-mode commands - Deprecated, Use patch:many or merge:many instead
     *
     * @deprecated Use patch:many or merge:many instead
     * @param string $batchCommand Name of command to run in batch mode (patch or merge)
     * @param array $options
     * @option $working-directory Working directory to check out repositories
     * @option $patch-source-directory Source directory for all collected patches
     * @option $patch-name Name of the directory where the patch code resides
     * @option $branch-name Name of the feature branch to be created
     * @option $halt-before-commit Pause before changes are committed, asks to continue
     * @option $source Source branch name for merge (e.g. feature branch)
     * @option $dry-run Show what would be done without executing
     * @option $filter Filter repositories (path:pattern or topic:tag, can be used multiple times)
     * @option $create-mr Create GitLab merge request after pushing
     * @return int exit code
     * @throws TaskException
     */
    public function batch(string $batchCommand, array $options = [
        'working-directory|d' => null,
        'patch-source-directory|s' => null,
        'patch-name|p' => 'template',
        'branch-name' => null,
        'halt-before-commit' => false,
        'source' => null,
        'dry-run' => false,
        'filter' => [],
        'create-mr' => false,
    ]): int
    {
        $this->io()->warning('The "batch" command is deprecated. Use "patch:many" or "merge:many" instead.');

        if ($batchCommand === 'patch') {
            return $this->patchMany($options['patch-name'], [
                'branch-name' => $options['branch-name'],
                'halt-before-commit' => $options['halt-before-commit'],
                'dry-run' => $options['dry-run'],
                'filter' => $options['filter'],
                'create-mr' => $options['create-mr'],
            ]);
        }

        if ($batchCommand === 'merge') {
            return $this->mergeMany($options['source'] ?? '', [
                'dry-run' => $options['dry-run'],
                'filter' => $options['filter'],
            ]);
        }

        $this->io()->error('Unknown batch command: ' . $batchCommand . '. Use "patch" or "merge".');
        return 1;
    }

    /**
     * Taskrunner steps to apply the patch
     *
     * @param array $options Options array passed from parent task
     * @return bool true = patch applied
     * @throws TaskException
     */
    protected function runPatch(array $options): bool
    {
        $repositoryName = pathinfo($options['repository-url'], PATHINFO_FILENAME);

        // Set working directory
        $this->say('Switch to working directory ' . $options['working-directory']);
        chdir($options['working-directory']);

        // Clone repo or use existing repository in workspace
        if (false === is_dir($repositoryName)) {
            $this->say('Clone repository');
            $result = $this->taskGitStack()
                ->cloneRepo($options['repository-url'], $repositoryName)
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                ->run();
            if ($result->wasSuccessful() !== true) {
                throw new TaskException($this, 'Cloning failed - '
                    . 'Maybe wrong URI or missing access rights');
            }
        }
        chdir($repositoryName);
        $currentDirectory = getcwd();
        $this->say('Use repository in ' . $currentDirectory);

        // Checkout main branch, update, create new feature branch
        $this->say('Create new branch ' . $options['branch-name']);
        $result = $this->taskGitStack()
            ->checkout($options['source-branch'])
            ->pull()
            ->checkout('-b ' . $options['branch-name'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Branch creation failed - '
                . 'Tried to create ' . $options['branch-name']
                . ' from ' . $options['source-branch']);
        }

        // Patch!
        $this->say('Run patch script');
        try {
            $patchDir = $options['patch-source-directory'] . $options['patch-name'];
            $resolver = new PatchProviderResolver();
            $provider = $resolver->resolve($patchDir);
            $output = $provider->execute($patchDir, $currentDirectory);
            $this->say($output);
        } catch (\RuntimeException $e) {
            throw new TaskException($this, $e->getMessage());
        } catch (Exception $e) {
            throw new TaskException($this, 'Patch script execution failed');
        }
        chdir($currentDirectory);

        // Check for changes
        $this->say('Detect changes');
        $fileChanges = shell_exec('git status -s');
        if (empty($fileChanges)) {
            $this->say('Nothing to commit, no changes in repository');
            return false;
        }

        // Halt for manual review before commit
        if ($options['halt-before-commit']) {
            $this->io()->text('Halt for manual review');
            $this->io()->text('Working directory: ' . PHP_EOL . $currentDirectory);
            $this->io()->text('File changes: ' . PHP_EOL . $fileChanges);
            $question = $this->io()->confirm('Continue?', true);
            if ($question === false) {
                return false;
            }
        }

        // Configure Git user if set in environment
        $botGitName = getenv('BOT_GIT_NAME');
        $botGitEmail = getenv('BOT_GIT_EMAIL');
        if (!empty($botGitName) && !empty($botGitEmail)) {
            $this->say('Configure Git user: ' . $botGitName . ' <' . $botGitEmail . '>');
            shell_exec('git config user.name ' . escapeshellarg($botGitName));
            shell_exec('git config user.email ' . escapeshellarg($botGitEmail));
        }

        // Commit changes
        $this->say('Commit changes');
        $commitMessage = file_get_contents($options['patch-source-directory'] . $options['patch-name'] . '/commit-message.txt');
        $result = $this->taskGitStack()
            ->add('-A')
            ->commit($commitMessage)
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Commit failed');
        }

        // Push branch
        $this->say('Push branch');
        $result = $this->taskGitStack()
            ->push('origin', $options['branch-name'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Push failed');
        }

        return true;
    }

    /**
     * Taskrunner steps to apply the merge
     *
     * @param array $options Options array passed from parent task
     * @return bool true = merge done
     * @throws TaskException
     */
    protected function runMerge(array $options): bool
    {
        $repositoryName = pathinfo($options['repository-url'], PATHINFO_FILENAME);

        // Set working directory
        $this->say('Switch to working directory ' . $options['working-directory']);
        chdir($options['working-directory']);

        // Clone repo or use existing repository
        if (false === is_dir($repositoryName)) {
            $this->say('Clone repository');
            $result = $this->taskGitStack()
                ->cloneRepo($options['repository-url'], $repositoryName)
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                ->run();
            if ($result->wasSuccessful() !== true) {
                throw new TaskException($this, 'Cloning failed - '
                    . 'Maybe wrong URI or missing access rights');
            }
        }
        chdir($repositoryName);
        $currentDirectory = getcwd();
        $this->say('Use repository in ' . $currentDirectory);

        // Fetch branches - <source> & <target>
        $this->say('Fetch branch ' . $options['source']);
        $result = $this->taskGitStack()
            ->checkout($options['source'] . ' --')
            ->pull()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Source branch does not exist');
        }
        $this->say('Fetch branch ' . $options['target']);
        $result = $this->taskGitStack()
            ->checkout($options['target'] . ' --')
            ->pull()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Target branch does not exist');
        }

        $mergeStatus = shell_exec('git merge-base --is-ancestor ' . escapeshellcmd($options['source']) . ' ' . escapeshellcmd($options['target']) . ' && echo "merged" || echo "tbd"');
        if (trim($mergeStatus) === 'merged') {
            $this->say('Nothing to merge - Everything up-to-date');
            return false;
        }

        // Merge!
        $this->say('Merge branches');
        $result = $this->taskGitStack()
            ->merge($options['source'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Merge conflict');
        }

        // Push branch
        $this->say('Push branch');
        $result = $this->taskGitStack()
            ->push('origin', $options['target'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        if ($result->wasSuccessful() !== true) {
            throw new TaskException($this, 'Push failed');
        }

        return true;
    }

    /**
     * Create a temporary directory
     *
     * @return string Directory path
     */
    protected function getTemporaryDirectory(): string
    {
        $result = $this->taskTmpDir('tmp_patchbot')
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        return $result['path'] ?? '';
    }

    /**
     * Overwrite the say method to be less verbose
     *
     * @param string $text
     * @return void
     */
    protected function say($text): void
    {
        if ($this->io()->isVerbose()) {
            parent::say($text);
        }
    }

    /**
     * Filter repositories based on filter expressions
     *
     * @param array $repositories List of repositories
     * @param array $filters Filter expressions (path:pattern or topic:tag)
     * @return array Filtered repositories
     */
    protected function filterRepositories(array $repositories, array $filters): array
    {
        foreach ($filters as $filter) {
            if (!str_contains($filter, ':')) {
                $this->io()->warning('Invalid filter format: ' . $filter . ' (expected path:pattern or topic:tag)');
                continue;
            }

            [$type, $value] = explode(':', $filter, 2);

            $repositories = match ($type) {
                'path' => array_filter($repositories, fn ($repo) => fnmatch($value, $repo['path_with_namespace'])),
                'topic' => array_filter($repositories, fn ($repo) => in_array($value, $repo['topics'] ?? [])),
                default => $repositories,
            };

            if ($type !== 'path' && $type !== 'topic') {
                $this->io()->warning('Unknown filter type: ' . $type . ' (supported: path, topic)');
            }
        }

        return array_values($repositories);
    }

    /**
     * Create a GitLab merge request
     *
     * @param string $repositoryUrl Repository URL (SSH or HTTPS)
     * @param string $sourceBranch Feature branch name
     * @param string $targetBranch Target branch name
     * @param string $commitMessage Commit message (first line used as title)
     * @return string|null MR URL on success, null on failure
     */
    protected function createMergeRequest(
        string $repositoryUrl,
        string $sourceBranch,
        string $targetBranch,
        string $commitMessage
    ): ?string {
        $token = getenv('GITLAB_TOKEN');
        if (empty($token)) {
            $this->io()->warning('Cannot create MR: GITLAB_TOKEN not set');
            return null;
        }

        $gitlabUrl = getenv('GITLAB_URL') ?: 'https://gitlab.com';
        $projectPath = $this->extractProjectPath($repositoryUrl);

        if (empty($projectPath)) {
            $this->io()->warning('Cannot create MR: unable to extract project path from URL');
            return null;
        }

        // First line of commit message is title, rest is description
        $lines = explode("\n", trim($commitMessage));
        $title = $lines[0];
        $description = count($lines) > 1 ? implode("\n", array_slice($lines, 1)) : '';

        $this->say('Creating merge request for ' . $projectPath);

        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => rtrim($gitlabUrl, '/'),
                'headers' => [
                    'PRIVATE-TOKEN' => $token,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $response = $client->post('/api/v4/projects/' . urlencode($projectPath) . '/merge_requests', [
                'json' => [
                    'source_branch' => $sourceBranch,
                    'target_branch' => $targetBranch,
                    'title' => $title,
                    'description' => $description,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['web_url'] ?? null;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->io()->error('Failed to create MR: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract project path from repository URL
     *
     * @param string $url Repository URL (SSH or HTTPS)
     * @return string|null Project path (e.g., "user/repo")
     */
    protected function extractProjectPath(string $url): ?string
    {
        // SSH format: git@gitlab.com:user/repo.git
        if (preg_match('/^git@[^:]+:(.+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1];
        }

        // HTTPS format: https://gitlab.com/user/repo.git
        if (preg_match('/^https?:\/\/[^\/]+\/(.+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Load repositories from config file and apply filters
     *
     * @param array|string $filters Filter expressions
     * @return array|null Repositories array, or null on error
     */
    protected function loadRepositories(array|string $filters = []): ?array
    {
        $configFile = 'repositories.json';

        if (false === is_file($configFile)) {
            $this->io()->error('Can not find file "' . $configFile . '". Run "patchbot discover" first.');
            return null;
        }

        $jsonContent = file_get_contents($configFile);
        $config = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->io()->error('Invalid JSON in ' . $configFile . ': ' . json_last_error_msg());
            return null;
        }

        $repositories = $config['repositories'] ?? [];

        if (empty($repositories)) {
            $this->io()->warning('No repositories found in ' . $configFile);
            return [];
        }

        // Apply filters
        $filters = is_array($filters) ? $filters : [$filters];
        $filters = array_filter($filters);
        if (!empty($filters)) {
            $repositories = $this->filterRepositories($repositories, $filters);
            if (empty($repositories)) {
                $this->io()->warning('No repositories match the filter criteria');
                return [];
            }
        }

        return $repositories;
    }

    /**
     * Print batch processing summary
     *
     * @param array $results Results array with success/skipped/failed counts
     * @param bool $isDryRun Whether this was a dry run
     * @param int $totalCount Total number of repositories
     * @param string $action Action verb (patched/merged)
     */
    protected function printBatchSummary(array $results, bool $isDryRun, int $totalCount, string $action): void
    {
        $this->io()->newLine();
        if ($isDryRun) {
            $this->io()->text($totalCount . ' repositories would be processed');
        } else {
            $total = $results['success'] + $results['skipped'] + $results['failed'];
            $this->io()->section('Summary');
            $this->io()->text($total . ' repositories processed');
            if ($results['success'] > 0) {
                $this->io()->text('  ✓ ' . $results['success'] . ' ' . $action);
            }
            if ($results['skipped'] > 0) {
                $this->io()->text('  - ' . $results['skipped'] . ' skipped (no changes)');
            }
            if ($results['failed'] > 0) {
                $this->io()->text('  ✗ ' . $results['failed'] . ' failed');
            }
        }
    }
}
