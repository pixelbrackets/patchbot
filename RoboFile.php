<?php
/**
 * Patchbot tasks (based on Robo)
 *
 *   ./vendor/bin/robo patch
 *
 */
class RoboFile extends \Robo\Tasks
{
    /**
     * Apply changes, commit changes, push
     *
     * @param array $options
     * @option $repository-url HTTPS URL of Git repository
     * @option $working-directory Working directory to checkout repositories
     * @option $patch-name Name of the directory where the patch code resides
     * @throws \Robo\Exception\TaskException
     */
    public function patch(array $options = [
        'repository-url|g' => null,
        'working-directory|d' => null,
        'patch-name|p' => 'template'
    ])
    {
        if (empty($options['repository-url'])) {
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

        // Checkout main branch, update, create new feature branch
        $patchBranch = date('Ymd') . '_' . 'patchbot_' . uniqid();
        $this->say('Create new branch ' . $patchBranch);
        $this->taskGitStack()
            ->checkout('master')
            ->pull()
            ->checkout('-b ' . $patchBranch)
            ->silent(true)
            ->run();

        // Patch!
        $this->say('Run patch script');
        try {
            $patchFile = __DIR__ . '/patches/' . $options['patch-name'] . '/patch.php';
            require_once($patchFile);
        } catch (Exception $e) {
            throw new \Robo\Exception\TaskException($this, 'Patch script execution failed');
        }
        chdir($currentDirectory);

        // Commit changes
        $this->say('Commit changes');
        if (empty(exec('git status -s'))) {
            $this->say('Nothing to commit, no changes in repository');
            return;
        }
        $commitMessage = file_get_contents(__DIR__ . '/patches/' . $options['patch-name'] . '/commit-message.txt');
        $this->taskGitStack()
            ->add('-A')
            ->commit($commitMessage)
            ->silent(true)
            ->run();

        // Push branch
        //$this->say('Push branch');
        $this->taskGitStack()
            ->push('origin', $patchBranch)
            ->silent(true)
            ->run();

        // Create PR
        // GitLab: `push` with push options https://docs.gitlab.com/ee/user/project/push_options.html#push-options-for-merge-requests & https://gitlab.com/gitlab-org/gitlab-foss/-/merge_requests/26752
        // GitHub: `request-pull` and manual work https://hackernoon.com/how-to-git-pr-from-the-command-line-a5b204a57ab1
        // Other: Show link to origin and ask to open it in a browser? (silent = false)
        $this->say('Create PR');
        $this->taskOpenBrowser($options['repository-url'])->run();
    }
}
