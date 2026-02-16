# Patchbot

![Logo](docs/icon.png)

[![Version](https://img.shields.io/packagist/v/pixelbrackets/patchbot.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/patchbot/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/patchbot?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)
[![Contribution](https://img.shields.io/badge/contributions_welcome-%F0%9F%94%B0-brightgreen.svg?labelColor=brightgreen&style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/-/blob/master/CONTRIBUTING.md)

Automate changes across multiple Git repositories — create branches, apply
patches, push, and open merge requests in batch.

![Screenshot](docs/screenshot.png)

## Why Patchbot?

You need to apply the same change to 20 repositories. Manually that means:
clone, branch, edit, commit, push, create merge request — times 20.

Maybe you need to rename a file in every repo, replace a deprecated URL in
all docs, add a package to all projects or run a migration script. Nothing a plain
[Git patch file](https://git-scm.com/docs/git-format-patch/2.7.6) could solve,
but something that can be automated with a script.

Patchbot does the repetitive parts for you. Write the change once as a patch
script, point Patchbot at your repositories, and let it create feature branches,
commit, push, and optionally open merge requests — across all of them.

Patchbot runs centrally on your machine or CI server and pushes changes out to
repositories. It is not a service that repositories pull from or run on their own.

Saving time, preventing careless mistakes and avoiding monotonous work.

Take a look at this
[blog post with real world examples](https://pixelbrackets.de/notes/distribute-patches-to-many-git-repositories-with-patchbot)
to see how Patchbot helps reduce technical debt across Git repositories.

## Key Features

- Batch git operations - Apply the same patch to 1, 20, or 300 repositories
- Auto-discovery - Scan a GitLab namespace to find all repositories automatically
- Merge request creation - Optionally create GitLab MRs after pushing (`--create-mr`)
- Repository filtering - Target specific repos by path pattern or GitLab topic (`--filter`)
- Dry-run mode - Preview what would happen without making changes (`--dry-run`)
- Custom git user - Push as a bot user instead of your personal account
- Multi-language patches - Write patch scripts in PHP, Shell, Python, or as Git diffs
- CI-ready - Run Patchbot as a scheduled GitLab CI pipeline

## Quick Start

```bash
# Create a new patch project using the skeleton
composer create-project pixelbrackets/patchbot-skeleton my-patches
cd my-patches

# Create a new patch
./vendor/bin/patchbot create "My first patch"

# Edit the patch script and commit message
# patches/my-first-patch/patch.php
# patches/my-first-patch/commit-message.txt

# Apply the patch to a repository
./vendor/bin/patchbot patch my-first-patch git@gitlab.com:user/repo.git
# Or apply to all repositories in repositories.json
./vendor/bin/patchbot patch-many my-first-patch --dry-run
```

## Requirements

- PHP
- Git

## Installation

Use the
[skeleton package](https://packagist.org/packages/pixelbrackets/patchbot-skeleton/)
to create a patch project right away:

```bash
composer create-project pixelbrackets/patchbot-skeleton my-patches
```

Or install Patchbot as a dependency in an existing project:

```bash
composer require pixelbrackets/patchbot
```

The user running Patchbot needs clone and push access to the target repositories.
SSH is the recommended protocol. See the
[walkthrough guide](docs/walkthrough.md#access-rights)
for details on configuring access.

## Usage

### Patch structure

Patchbot organizes patches in the `patches/` directory. Each patch directory
contains a patch file and a commit message (`commit-message.txt`):

```
patches/
|-- template/
|   |-- commit-message.txt
|   `-- patch.php
`-- update-changelog/
    |-- commit-message.txt
    `-- patch.sh
```

The patch file type is detected automatically by filename. Each patch directory
must contain exactly one of the following:

| File | Language | Execution |
|------|----------|-----------|
| `patch.php` | PHP | `php patch.php` |
| `patch.sh` | Shell | `bash patch.sh` |
| `patch.diff` | Git diff | `git apply patch.diff` |
| `patch.py` | Python | `python3 patch.py` |

The patch script runs in the root directory of the cloned target repository.
You can develop it incrementally by running it directly in any project directory,
for example `php <path-to-patch>/patch.php` or `bash <path-to-patch>/patch.sh`.

### Apply a patch

```bash
./vendor/bin/patchbot patch <patch-name> <repository-url>
```

Patchbot clones the repository, creates a feature branch, runs the patch script,
commits the changes, and pushes the branch to the remote.

```bash
# Apply patch "template" to a repository
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git

# Preview without making changes
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git --dry-run

# Create a GitLab merge request after pushing
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git --create-mr

# Use a custom source branch (default: main)
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git --source-branch=development

# Use a custom feature branch name
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git --branch-name=feature-1337

# Pause before committing (for manual review)
./vendor/bin/patchbot patch template git@gitlab.com:user/repo.git --halt-before-commit
```

### Batch apply patches

Apply a patch to all repositories listed in `repositories.json`:

```bash
./vendor/bin/patchbot patch-many <patch-name>
```

```bash
# Apply to all repositories
./vendor/bin/patchbot patch-many update-changelog

# Filter by path pattern
./vendor/bin/patchbot patch-many update-changelog --filter="path:my-org/*"

# Filter by GitLab topic
./vendor/bin/patchbot patch-many update-changelog --filter="topic:php"

# Combine filters, create MRs, and preview first
./vendor/bin/patchbot patch-many update-changelog --filter="topic:php" --create-mr --dry-run
```

After batch processing completes, a summary shows how many repositories were
patched, skipped, or failed.

### Discover repositories

Instead of adding repository URLs manually, let Patchbot discover them from
a GitLab namespace (group or user):

```bash
# Set up your GitLab token in .env
cp .env.example .env
# Edit .env with your GITLAB_TOKEN and GITLAB_NAMESPACE

# Discover repositories
./vendor/bin/patchbot discover

# Or specify the namespace directly
./vendor/bin/patchbot discover --gitlab-namespace=mygroup

# Overwrite existing repositories.json
./vendor/bin/patchbot discover --force
```

This creates a `repositories.json` file with all discovered repositories,
including their clone URLs and default branches.

### Merge feature branches

Patchbot creates feature branches by design, so changes can be reviewed and
tested by CI before merging. Use the merge commands when ready:

```bash
# Merge a branch into a target branch for one repository
./vendor/bin/patchbot merge <source-branch> <target-branch> <repository-url>

# Merge a branch into the default branch for all repositories
./vendor/bin/patchbot merge-many <source-branch>
```

```bash
# Examples
./vendor/bin/patchbot merge feature-add-license main git@gitlab.com:user/repo.git
./vendor/bin/patchbot merge-many feature-add-phpcs-rules
./vendor/bin/patchbot merge-many feature-add-phpcs-rules --dry-run
```

### Create a new patch

```bash
./vendor/bin/patchbot create "Add CHANGELOG file"
```

This generates a patch directory with the required files. Edit `patch.php`
with your change logic and `commit-message.txt` with the commit message.
See the [walkthrough guide](docs/walkthrough.md#writing-patches) for tips
on developing and testing patches.

### Command and options reference

**Commands**

| Command | Description |
|---------|-------------|
| `patch` | Apply changes, commit, push |
| `patch-many` | Apply a patch to all repositories |
| `merge` | Merge one branch into another, push |
| `merge-many` | Merge a branch into all repositories |
| `create` | Create a new patch |
| `discover` | Discover repositories from a GitLab namespace |

**Options**

| Option | Available in | Description |
|--------|--------------|-------------|
| `--dry-run` | patch, merge, patch-many, merge-many | Preview without making changes |
| `--create-mr` | patch, patch-many | Create GitLab merge request after pushing |
| `--filter` | patch-many, merge-many | Filter repositories by `path:glob` or `topic:name` |
| `--source-branch` | patch | Base branch for the feature branch (default: `main`) |
| `--branch-name` | patch, patch-many | Custom name for the feature branch |
| `--halt-before-commit` | patch, patch-many | Pause before committing for manual review |
| `--force` | discover | Overwrite existing `repositories.json` |
| `-v` / `-vvv` | all | Increase output verbosity |

### Custom git user

By default, commits use your system Git configuration. To push as a bot user,
set these environment variables:

```bash
BOT_GIT_NAME="Patchbot"
BOT_GIT_EMAIL="patchbot@example.com"
```

### GitLab CI

Run Patchbot as a scheduled GitLab CI pipeline instead of locally.
Copy `.gitlab-ci.example.yml` to your config repository and set the CI/CD
variables `GITLAB_TOKEN` and `GITLAB_NAMESPACE`.

## Source

https://gitlab.com/pixelbrackets/patchbot/

Mirror https://github.com/pixelbrackets/patchbot/

## License

GNU General Public License version 2 or later

The GNU General Public License can be found at http://www.gnu.org/copyleft/gpl.html.

## Author

Dan Kleine (<mail@pixelbrackets.de> / [@pixelbrackets](https://pixelbrackets.de))

See [CHANGELOG.md](./CHANGELOG.md)

## Contribution

This script is Open Source, so please use, share, patch, extend or fork it.

[Contributions](./CONTRIBUTING.md) are welcome!

## Feedback

Please send some [feedback](https://pixelbrackets.de/) and share how this
package has proven useful to you or how you may help to improve it.
