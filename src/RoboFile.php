<?php

namespace Pixelbrackets\Patchbot;

use Cocur\Slugify\Slugify;
use Pixelbrackets\Patchbot\Discovery\GitLabDiscovery;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Exception\TaskException;

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
     * @param array $options
     * @option $repository-url URI of Git repository (HTTPS/SSH/FILE)
     * @option $working-directory Working directory to check out repositories
     * @option $patch-source-directory Source directory for all collected patches
     * @option $patch-name Name of the directory where the patch code resides
     * @option $source-branch Name of the branch to create a new branch upon
     * @option $branch-name Name of the feature branch to be created
     * @option $halt-before-commit Pause before changes are committed, asks to continue
     * @option $dry-run Show what would be done without executing
     * @return int exit code
     * @throws TaskException
     */
    public function patch(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'patch-source-directory|s' => null,
        'patch-name|p' => 'template',
        'source-branch' => 'main',
        'branch-name' => null,
        'halt-before-commit' => false,
        'dry-run' => false,
    ]): int
    {
        if (empty($options['repository-url'])) {
            $this->io()->error('Missing arguments');
            return 1;
        }

        $options['patch-source-directory'] = ($options['patch-source-directory'] ?? getcwd() . '/patches') . '/';
        $options['working-directory'] = $options['working-directory'] ?? $this->getTemporaryDirectory();
        /** @noinspection NonSecureUniqidUsageInspection */
        $options['branch-name'] = $options['branch-name'] ?? date('Ymd') . '_' . 'patchbot_' . uniqid();
        $repositoryName = pathinfo($options['repository-url'], PATHINFO_FILENAME);

        // Print summary
        $this->io()->section($options['dry-run'] ? 'Patch (Dry Run)' : 'Patch');
        $this->io()->listing([
            'Patch: ' . $options['patch-name'],
            'Branch: ' . $options['branch-name'],
            'Repository: ' . $repositoryName . ' (' . $options['repository-url'] . ')'
        ]);

        if ($options['dry-run']) {
            $this->io()->text('[DRY-RUN] Would clone repository and create branch');
            $this->io()->text('[DRY-RUN] Would run patch script: ' . $options['patch-source-directory'] . $options['patch-name'] . '/patch.php');
            $this->io()->text('[DRY-RUN] Would commit and push changes');
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
        // Suggest next steps
        $this->say('Hint: Run `./vendor/bin/patchbot merge'
            . ' --source=' . $options['branch-name']
            . ' --target=<target branch>'
            . ' --repository-url=' . $options['repository-url'] . '` to merge the feature branch');

        return 0;
    }

    /**
     * Merge one branch into another, push
     *
     * @param array $options
     * @option $repository-url URI of Git repository (HTTPS/SSH/FILE)
     * @option $working-directory Working directory to check out repositories
     * @option $source Source branch name (e.g. feature branch)
     * @option $target Target branch name (e.g. main branch)
     * @option $dry-run Show what would be done without executing
     * @return int exit code
     * @throws TaskException
     */
    public function merge(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'source|s' => null,
        'target|t' => null,
        'dry-run' => false,
    ]): int
    {
        if (
            empty($options['repository-url']) ||
            empty($options['source']) ||
            empty($options['target'])
        ) {
            $this->io()->error('Missing arguments');
            return 1;
        }

        $options['working-directory'] = $options['working-directory'] ?? $this->getTemporaryDirectory();
        $repositoryName = pathinfo($options['repository-url'], PATHINFO_FILENAME);

        // Print summary
        $this->io()->section($options['dry-run'] ? 'Merge (Dry Run)' : 'Merge');
        $this->io()->listing([
            'Source Branch: ' . $options['source'],
            'Target Branch: ' . $options['target'],
            'Repository: ' . $repositoryName . ' (' . $options['repository-url'] . ')'
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
     * @param array $options
     * @option $patch-name Name of the patch, used as directory name
     * @return int exit code
     * @throws TaskException
     */
    public function create(array $options = [
        'patch-name|p' => null,
    ]): int
    {
        if (empty($options['patch-name'])) {
            $this->io()->error('Missing arguments');
            return 1;
        }

        $patchName = (new Slugify())->slugify($options['patch-name']);
        $patchDirectory = getcwd() . '/patches/' . $patchName;

        // Print summary
        $this->io()->section('Create');
        $this->io()->listing([
            'Patch: ' . $options['patch-name']
        ]);

        $this->say('Create patch ' . $patchName);
        if (is_dir($patchDirectory)) {
            $this->io()->error('Patch directory »' . $patchDirectory . '« already exists');
            return 1;
        }

        $this->taskCopyDir([__DIR__ . '/../patches/template/' => $patchDirectory])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

        $this->io()->success('Patch directory created');
        // Suggest next steps
        $this->say('- Edit patch.php & commit-message.txt in ' . $patchDirectory);
        $this->say('- Run `./vendor/bin/patchbot patch --patch-name='
            . $patchName
            . ' --repository-url=<git repository url>` to apply the patch to a repository');

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
     * Run batch-mode commands
     *
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
    ]): int
    {
        $workingDirectory = getcwd();
        $configFile = 'repositories.json';

        if (false === is_file($configFile)) {
            $this->io()->error('Can not find file "' . $configFile . '". Run "patchbot discover" first.');
            return 1;
        }

        // Parse JSON config
        $jsonContent = file_get_contents($configFile);
        $config = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->io()->error('Invalid JSON in ' . $configFile . ': ' . json_last_error_msg());
            return 1;
        }

        $repositories = $config['repositories'] ?? [];

        if (empty($repositories)) {
            $this->io()->warning('No repositories found in ' . $configFile);
            return 0;
        }

        // Apply filters
        $filters = is_array($options['filter']) ? $options['filter'] : [$options['filter']];
        $filters = array_filter($filters); // remove empty values
        if (!empty($filters)) {
            $repositories = $this->filterRepositories($repositories, $filters);
            if (empty($repositories)) {
                $this->io()->warning('No repositories match the filter criteria');
                return 0;
            }
        }

        $isDryRun = $options['dry-run'];

        if ($isDryRun) {
            $this->io()->section('Dry Run');
        }

        $results = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        if ($batchCommand === 'patch') {
            foreach ($repositories as $repository) {
                if ($isDryRun) {
                    $this->io()->text('[DRY-RUN] Would patch: ' . $repository['path_with_namespace'] . ' (' . $repository['default_branch'] . ')');
                    continue;
                }
                chdir($workingDirectory); // reset working directory
                try {
                    $result = $this->runPatch([
                        'repository-url' => $repository['clone_url_ssh'],
                        'working-directory' => $options['working-directory'] ?? $this->getTemporaryDirectory(),
                        'patch-source-directory' => ($options['patch-source-directory'] ?? getcwd() . '/patches') . '/',
                        'patch-name' => $options['patch-name'],
                        'source-branch' => $repository['default_branch'],
                        'branch-name' => $options['branch-name'] ?? date('Ymd') . '_patchbot_' . uniqid(),
                        'halt-before-commit' => $options['halt-before-commit'],
                    ]);
                    if ($result) {
                        $results['success']++;
                        $this->io()->success('Patched: ' . $repository['path_with_namespace']);
                    } else {
                        $results['skipped']++;
                        $this->io()->text('Skipped: ' . $repository['path_with_namespace'] . ' (no changes)');
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $this->io()->error('Failed: ' . $repository['path_with_namespace'] . ' - ' . $e->getMessage());
                }
            }
        }

        if ($batchCommand === 'merge') {
            foreach ($repositories as $repository) {
                if ($isDryRun) {
                    $this->io()->text('[DRY-RUN] Would merge: ' . $options['source'] . ' -> ' . $repository['default_branch'] . ' in ' . $repository['path_with_namespace']);
                    continue;
                }
                chdir($workingDirectory); // reset working directory
                try {
                    $result = $this->runMerge([
                        'repository-url' => $repository['clone_url_ssh'],
                        'working-directory' => $options['working-directory'] ?? $this->getTemporaryDirectory(),
                        'source' => $options['source'],
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
        }

        // Print summary
        $this->io()->newLine();
        if ($isDryRun) {
            $this->io()->text(count($repositories) . ' repositories would be processed');
        } else {
            $total = $results['success'] + $results['skipped'] + $results['failed'];
            $this->io()->section('Summary');
            $this->io()->text($total . ' repositories processed');
            if ($results['success'] > 0) {
                $this->io()->text('  ✓ ' . $results['success'] . ' ' . ($batchCommand === 'patch' ? 'patched' : 'merged'));
            }
            if ($results['skipped'] > 0) {
                $this->io()->text('  - ' . $results['skipped'] . ' skipped (no changes)');
            }
            if ($results['failed'] > 0) {
                $this->io()->text('  ✗ ' . $results['failed'] . ' failed');
            }
        }

        return $results['failed'] > 0 ? 1 : 0;
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
            $patchFile = $options['patch-source-directory'] . $options['patch-name'] . '/patch.php';
            $output = shell_exec('php ' . escapeshellcmd($patchFile));
            $this->say($output);
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
                'path' => array_filter($repositories, fn($repo) => fnmatch($value, $repo['path_with_namespace'])),
                'topic' => array_filter($repositories, fn($repo) => in_array($value, $repo['topics'] ?? [])),
                default => $repositories,
            };

            if ($type !== 'path' && $type !== 'topic') {
                $this->io()->warning('Unknown filter type: ' . $type . ' (supported: path, topic)');
            }
        }

        return array_values($repositories);
    }
}
