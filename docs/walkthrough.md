# Patchbot Walkthrough

This guide walks you through a typical Patchbot workflow — from setting up
access, writing your first patch, applying it to repositories, and running
Patchbot in CI. For a quick overview see the [README](../README.md).

## 1. Set Up Access

The user running Patchbot needs to be allowed to clone and push to all
target repositories.

Patchbot supports all protocols that Git supports natively:
[FILE, HTTP/HTTPS, SSH](https://git-scm.com/book/en/v2/Git-on-the-Server-The-Protocols).
The recommended protocol is SSH.

### HTTPS Credentials

Git by default does not store any credentials. So *every connection* to a
repository by HTTPS will prompt for a username and password.

To avoid these password prompts when using HTTPS URIs with Patchbot
you have two options:

- Allow Git to store credentials in memory for some time
  - The password prompt then pops up once only for each host
  - Example command to keep the credentials in memory for 15 minutes:
    ```bash
    git config --global credential.helper 'cache --timeout=900'
    ```
- Force Git to use SSH protocol checkouts instead of HTTP/HTTPS
  - Has to be configured for each host
  - Example commands to set up the replacements for GitHub, GitLab & BitBucket:
    ```bash
    git config --global url."ssh://git@github.com/".insteadOf "https://github.com/"
    git config --global url."ssh://git@gitlab.com/".insteadOf "https://gitlab.com/"
    git config --global url."ssh://git@bitbucket.org/".insteadOf "https://bitbucket.org/"
    ```

## 2. Create a Patch Project

The easiest way to get started is the
[skeleton package](https://packagist.org/packages/pixelbrackets/patchbot-skeleton/):

```bash
composer create-project pixelbrackets/patchbot-skeleton my-patches
cd my-patches
```

This gives you a ready-made project structure:

```
my-patches/
|-- composer.json
|-- patches/
|   `-- template/
|       |-- commit-message.txt
|       `-- patch.php
`-- .editorconfig
```

## 3. Write a Patch

Create a new patch directory:

```bash
./vendor/bin/patchbot create "Add CHANGELOG file"
```

This generates `patches/add-changelog-file/` with two files to edit:

- `patch.php` — The script that makes the actual changes
- `commit-message.txt` — The commit message used when applying the patch

#### Supported patch file types

Patchbot detects the patch type by filename. Replace the generated `patch.php`
with any of the supported types:

| File | Language | Execution |
|------|----------|-----------|
| `patch.php` | PHP | `php patch.php` |
| `patch.sh` | Shell | `bash patch.sh` |
| `patch.diff` | Git diff | `git apply patch.diff` |
| `patch.py` | Python | `python3 patch.py` |

Each patch directory must contain exactly one patch file. Using multiple patch
files in the same directory (e.g. both `patch.php` and `patch.sh`) is not
allowed and will result in an error.

Patchbot runs the patch script isolated in the root directory of the cloned
target repository. This means you can develop the script incrementally by
running it directly in any project directory:

```bash
cd /path/to/some-project
php /path/to/my-patches/patches/add-changelog-file/patch.php
# or
bash /path/to/my-patches/patches/add-changelog-file/patch.sh
```

Check the result, adjust the script, repeat. When the patch works as
expected, use Patchbot to distribute it.

## 4. Apply a Patch

Apply the patch to a single repository:

```bash
./vendor/bin/patchbot patch add-changelog-file git@gitlab.com:user/repo.git
```

Patchbot will clone the repository, create a feature branch, run the patch
script, commit the changes, and push the branch to the remote.

Use `--dry-run` to preview what would happen without making any changes:

```bash
./vendor/bin/patchbot patch add-changelog-file git@gitlab.com:user/repo.git --dry-run
```

## 5. Apply to Many Repositories

For batch operations, first create a `repositories.json` file. You can either
write it manually or let Patchbot discover repositories from GitLab:

```bash
# Set up your GitLab token in .env
cp .env.example .env
# Edit .env with your GITLAB_TOKEN and GITLAB_NAMESPACE

# Discover all repositories in a namespace
./vendor/bin/patchbot discover --gitlab-namespace=mygroup
```

Then apply the patch to all discovered repositories:

```bash
./vendor/bin/patchbot patch-many add-changelog-file
```

Use filters to target specific repositories:

```bash
# Only repositories matching a path pattern
./vendor/bin/patchbot patch-many add-changelog-file --filter="path:mygroup/typo3-*"

# Only repositories with a specific GitLab topic
./vendor/bin/patchbot patch-many add-changelog-file --filter="topic:php"
```

After batch processing completes, a summary shows how many repositories were
patched, skipped, or failed.

## 6. Review and Merge

Patchbot always creates a feature branch rather than committing directly to
existing branches. This way you can review the changes and let CI run tests
before merging.

To create a GitLab merge request automatically, add `--create-mr`:

```bash
./vendor/bin/patchbot patch-many add-changelog-file --create-mr
```

When ready to merge, use the merge commands:

```bash
# Merge a single repository
./vendor/bin/patchbot merge feature-add-changelog-file main git@gitlab.com:user/repo.git

# Merge across all repositories
./vendor/bin/patchbot merge-many feature-add-changelog-file
```

## 7. Share Patches

The patches in your project are probably specific to your organisation or
domain. The best way to share them is to share the entire patch project as
a Git repository.

To share a single general-purpose patch, you can create a GitHub Gist.

Example command using the CLI tool [gist](https://github.com/defunkt/gist)
to upload the `template` patch:

```bash
cd patches/template/
gist -d "Patchbot Patch template - Just a template without changes" patch.php commit-message.txt
```

### Importing a Shared Patch

Copy & paste all files manually to import an existing patch from another source.

If the source is a Git repository then a Git clone command is sufficient.

Example command importing a Gist as patch `add-editorconfig`:

```bash
git clone --depth=1 https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745 patches/add-editorconfig/
rm -r patches/add-editorconfig/.git
```

## Running in GitLab CI

Instead of running Patchbot locally, you can set it up as a scheduled
GitLab CI pipeline:

1. Copy `.gitlab-ci.example.yml` to `.gitlab-ci.yml` in your config repository
2. Set CI/CD variables: `GITLAB_TOKEN`, `GITLAB_NAMESPACE`
3. Trigger manually via GitLab UI (CI/CD > Pipelines > Run pipeline)

See the [example configuration](../.gitlab-ci.example.yml) for details.

## Tips

### Custom Git User

By default, commits use your system Git configuration. To push as a bot
user, set these environment variables (in `.env` or your shell):

```bash
BOT_GIT_NAME="Patchbot"
BOT_GIT_EMAIL="patchbot@example.com"
```

### Verbosity and Debugging

Add `-v` to any command for more output, or `-vvv` for full debugging.
This will show all steps and Git commands applied by Patchbot.
