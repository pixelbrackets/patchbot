<?php

namespace Pixelbrackets\Patchbot;

use Cocur\Slugify\Slugify;
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
     * @option $working-directory Working directory to checkout repositories
     * @option $patch-source-directory Source directory for all collected patches
     * @option $patch-name Name of the directory where the patch code resides
     * @option $source-branch Name of the branch to create a new branch upon
     * @option $branch-name Name of the feature branch to be created
     * @option $halt-before-commit Pause before changes are commited, asks to continue
     * @return int exit code
     * @throws TaskException
     */
    public function patch(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'patch-source-directory|s' => null,
        'patch-name|p' => 'template',
        'source-branch' => 'master', // rename to main in next mayor release
        'branch-name' => null,
        'halt-before-commit' => false,
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
        $this->io()->section('Patch');
        $this->io()->listing([
            'Patch: ' . $options['patch-name'],
            'Branch: ' . $options['branch-name'],
            'Repository: ' . $repositoryName . ' (' . $options['repository-url'] . ')'
        ]);

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
        $this->io()->block('Hint: Run `./vendor/bin/patchbot merge'
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
     * @option $working-directory Working directory to checkout repositories
     * @option $source Source branch name
     * @option $target Target branch name
     * @return int exit code
     * @throws TaskException
     */
    public function merge(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'source|s' => null,
        'target|t' => null
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

        $workingDirectory = $options['working-directory'] ?? $this->getTemporaryDirectory();
        $repositoryName = pathinfo($options['repository-url'], PATHINFO_FILENAME);

        // Print summary
        $this->io()->section('Merge');
        $this->io()->listing([
            'Source Branch: ' . $options['source'],
            'Target Branch: ' . $options['target'],
            'Repository: ' . $repositoryName . ' (' . $options['repository-url'] . ')'
        ]);

        // Set working directory
        $this->say('Switch to working directory ' . $workingDirectory);
        chdir($workingDirectory);

        // Clone repo or use existing repository
        if (false === is_dir($repositoryName)) {
            $this->say('Clone repository');
            $this->taskGitStack()
                ->cloneRepo($options['repository-url'], $repositoryName)
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
                ->run();
        }
        chdir($repositoryName);
        $currentDirectory = getcwd();
        $this->say('Use repository in ' . $currentDirectory);

        // Fetch branches
        $this->say('Fetch branch ' . $options['source']);
        $this->taskGitStack()
            ->checkout($options['source'] . ' --')
            ->pull()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();
        $this->say('Fetch branch ' . $options['target']);
        $this->taskGitStack()
            ->checkout($options['target'] . ' --')
            ->pull()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

        // Merge!
        $this->say('Merge branches');
        $this->taskGitStack()
            ->merge($options['source'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

        // Push branch
        $this->say('Push branch');
        $this->taskGitStack()
            ->push('origin', $options['target'])
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

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
        $this->say('- Edit patch.php & commit-message.txt in ' . $patchDirectory);
        $this->say('- Run `./vendor/bin/patchbot patch --patch-name='
            . $patchName
            . ' --repository-url=<git repository url>` to apply the patch to a repository');

        return 0;
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
}
