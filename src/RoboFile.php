<?php

namespace Pixelbrackets\Patchbot;

use Cocur\Slugify\Slugify;

/**
 * Patchbot tasks (based on Robo)
 *
 *   ./vendor/bin/patchbot patch
 *
 */
class RoboFile extends \Robo\Tasks
{
    protected $initialWorkingDirectory = null;

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
     * @throws \Robo\Exception\TaskException
     */
    public function patch(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'patch-source-directory|s' => null,
        'patch-name|p' => 'template',
        'source-branch' => 'master', // rename to main in next mayor release
        'branch-name' => null,
        'halt-before-commit' => false,
    ])
    {
        if (empty($options['repository-url'])) {
            $this->say('Missing arguments');
            return;
        }

        // Set working directory
        $patchSourceDirectory = ($options['patch-source-directory'] ?? getcwd() . '/patches/') . '/';
        $workingDirectory = $options['working-directory'] ?? $this->_tmpDir();
        $this->say('Switch to working directory ' . $workingDirectory);
        chdir($workingDirectory);

        // Clone repo or use existing repository
        $repositoryName = basename($options['repository-url']);
        if (false === is_dir($repositoryName)) {
            $this->say('Clone repository');
            $gitClone = $this->taskGitStack()
                ->cloneRepo($options['repository-url'], $repositoryName)
                ->silent(true)
                ->run();

            if ($gitClone->wasSuccessful() !== true) {
                throw new \Robo\Exception\TaskException($this, 'Cloning failed');
            }
        }
        chdir($repositoryName);
        $currentDirectory = getcwd();
        $this->say('Use repository in ' . $currentDirectory);

        // Checkout main branch, update, create new feature branch
        $patchBranch = $options['branch-name'] ?? date('Ymd') . '_' . 'patchbot_' . uniqid();
        $this->say('Create new branch ' . $patchBranch);
        $this->taskGitStack()
            ->checkout($options['source-branch'])
            ->pull()
            ->checkout('-b ' . $patchBranch)
            ->silent(true)
            ->run();

        // Patch!
        $this->say('Run patch script');
        try {
            $patchFile = $patchSourceDirectory . $options['patch-name'] . '/patch.php';
            $output = shell_exec('php ' . escapeshellcmd($patchFile));
            echo $output;
        } catch (Exception $e) {
            throw new \Robo\Exception\TaskException($this, 'Patch script execution failed');
        }
        chdir($currentDirectory);

        // Check for changes
        $this->say('Commit changes');
        $fileChanges = exec('git status -s');
        if (empty($fileChanges)) {
            $this->say('Nothing to commit, no changes in repository');
            return;
        }

        // Halt for manual review before commit
        if ($options['halt-before-commit']) {
            $this->say('Halt for manual review');
            $this->say('Working directory: ' . PHP_EOL . $currentDirectory);
            $this->say('File changes: ' . PHP_EOL . $fileChanges);
            $question = $this->io()->confirm('Continue?', true);
            if ($question === false) {
                return;
            }
        }

        // Commit changes
        $this->say('Commit changes');
        $commitMessage = file_get_contents($patchSourceDirectory . $options['patch-name'] . '/commit-message.txt');
        $this->taskGitStack()
            ->add('-A')
            ->commit($commitMessage)
            ->silent(true)
            ->run();

        // Push branch
        $this->say('Push branch');
        $this->taskGitStack()
            ->push('origin', $patchBranch)
            ->silent(true)
            ->run();

        // Suggest next steps
        $this->say('Patch applied');
        $this->say('- Run `./vendor/bin/patchbot merge --source='
            . $patchBranch
            . ' --target=<target branch>'
            . ' --repository-url=<git repository url>` to merge the feature branch');
    }

    /**
     * Merge one branch into another, push
     *
     * @param array $options
     * @option $repository-url URI of Git repository (HTTPS/SSH/FILE)
     * @option $working-directory Working directory to checkout repositories
     * @option $source Source branch name
     * @option $target Target branch name
     * @throws \Robo\Exception\TaskException
     */
    public function merge(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'source|s' => null,
        'target|t' => null
    ])
    {
        if (
            empty($options['repository-url']) ||
            empty($options['source']) ||
            empty($options['target'])
        ) {
            $this->say('Missing arguments');
            return;
        }

        // Set working directory
        $workingDirectory = $options['working-directory'] ?? $this->_tmpDir();
        $this->say('Switch to working directory ' . $workingDirectory);
        chdir($workingDirectory);

        // Clone repo or use existing repository
        $repositoryName = basename($options['repository-url']);
        if (false === is_dir($repositoryName)) {
            $this->say('Clone repository');
            $this->taskGitStack()
                ->cloneRepo($options['repository-url'], $repositoryName)
                ->silent(true)
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
            ->silent(true)
            ->run();
        $this->say('Fetch branch ' . $options['target']);
        $this->taskGitStack()
            ->checkout($options['target'] . ' --')
            ->pull()
            ->silent(true)
            ->run();

        // Merge!
        $this->say('Merge branches');
        $this->taskGitStack()
            ->merge($options['source'])
            ->silent(true)
            ->run();

        // Push branch
        $this->say('Push branch');
        $this->taskGitStack()
            ->push('origin', $options['target'])
            ->silent(true)
            ->run();
    }

    /**
     * Create a new patch
     *
     * @param array $options
     * @option $patch-name Name of the patch, used as directory name
     * @throws \Robo\Exception\TaskException
     */
    public function create(array $options = [
        'patch-name|p' => null,
    ])
    {
        if (empty($options['patch-name'])) {
            $this->say('Missing arguments');
            return;
        }
        $patchName = (new Slugify())->slugify($options['patch-name']);

        $this->say('Create patch ' . $patchName);

        $patchDirectory = getcwd() . '/patches/' . $patchName;

        if (is_dir($patchDirectory)) {
            $this->say('Patch directory »' . $patchDirectory . '« already exists');
            return;
        }

        $this->_copyDir(__DIR__ . '/../patches/template/', $patchDirectory);

        $this->say('Patch directory created');
        $this->say('- Edit patch.php & commit-message.txt in ' . $patchDirectory);
        $this->say('- Run `./vendor/bin/patchbot patch --patch-name='
            . $patchName
            . ' --repository-url=<git repository url>` to apply the patch to a repository');
    }
}
