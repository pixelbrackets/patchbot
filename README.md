# Patchbot

![Logo](docs/icon.png)

[![Version](https://img.shields.io/packagist/v/pixelbrackets/patchbot.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/patchbot/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/patchbot?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)
[![Contribution](https://img.shields.io/badge/contributions_welcome-%F0%9F%94%B0-brightgreen.svg?labelColor=brightgreen&style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/-/blob/master/CONTRIBUTING.md)

A tool to automate the distribution of patches to various Git repositories.

![Screenshot](docs/screenshot.png)

_⭐ You like this package? Please star it or send a tweet. ⭐_

## Vision

This project provides a tool to distribute changes to a several Git repositories
with as little manual work as possible.

The need for this came up when I had to apply the same manual changes to many
of my repositories:

- Rename files having a certain name pattern, remove a line of code
  only if a condition matches, replace a link in all documents, execute another
  tool which then changes files and so on. Nothing a plain 
  [Git patch file](https://git-scm.com/docs/git-format-patch/2.7.6) could solve,
  but something that could be automated nevertheless with a migration script.
- Create a feature branch, commit all changes with a good commit message,
  push the branch, wait for tests to turn green, open a pull request.
- Repeat the same steps in many other repositories.

The idea is to do the changes only once and move the repetitions to a tool.
Saving time, preventing careless mistakes and shun monotonous work.

📝 Take a look at this
[blog post with real world examples](https://pixelbrackets.de/notes/distribute-patches-to-many-git-repositories-with-patchbot)
and how Patchbot helps to reduce technical debt across your own Git
repositories.

See [»Usage«](#usage) for example commands.

The package follows the KISS principle.

## Requirements

- PHP
- Git

## Installation

💡 Use the 
[skeleton package](https://packagist.org/packages/pixelbrackets/patchbot-skeleton/)
to create an example project right away.

- `composer create-project pixelbrackets/patchbot-skeleton`

Packagist Entry to install Patchbot only
https://packagist.org/packages/pixelbrackets/patchbot/

- `composer require pixelbrackets/patchbot`

### Access rights

🔑 *The user running Patchbot needs to have access to the target repository.*

Make sure that the user running Patchbot is allowed to clone and push to
all target repositories.

Patchbot allows all protocols for connections to remotes which are supported
by Git natively:
[FILE, HTTP/HTTPS, SSH](https://git-scm.com/book/en/v2/Git-on-the-Server-The-Protocols)

The recommended protocol is SSH.

#### HTTPS Credentials

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
  - Example commands to set up the replacements for GitHub, GitLab & BitBucket
    ```bash
    git config --global url."ssh://git@github.com/".insteadOf "https://github.com/"
    git config --global url."ssh://git@gitlab.com/".insteadOf "https://gitlab.com/"
    git config --global url."ssh://git@bitbucket.org/".insteadOf "https://bitbucket.org/"
    ```

## Source

https://gitlab.com/pixelbrackets/patchbot/

Mirror https://github.com/pixelbrackets/patchbot/

## Usage

Patchbot patches a given Git repository.

This means it will clone the repository, create a feature branch,
run a given PHP patch script, commit the changes with a given commit message
and push the branch to the remote.

Patchbot uses a lean file structure to organize patches (see
[skeleton package](https://packagist.org/packages/pixelbrackets/patchbot-skeleton/)).

The directory `patches` contains a collection of all your “patch directories“.

Each patch directory always contains at least a PHP script named `patch.php`
and a commit message named `commit-message.txt`. 

Example file structure:
```
.
|-- patches
|   |-- template
|   |   |-- commit-message.txt
|   |   `-- patch.php
|   `-- yet-another-patch
|       |-- commit-message.txt
|       `-- patch.php
|-- vendor
|   `-- bin
|       `-- patchbot
|-- composer.json
`-- README.md
```

This way a migration script may be created once and applied in a row to
many repositories or ad hoc every time the need arises.

### Apply patch

Pass the name of the patch directory as `patch-name` and the Git repository as
`repository-url` to the `patchbot` script.

Example command applying the patch script in directory `template` to
the repository `https://git.example.com/repository`:
```bash
./vendor/bin/patchbot patch --patch-name=template --repository-url=https://git.example.com/repository
```

Example command applying the patch script in directory `template` to
the repository `ssh://git@git.example.com/repository.git`:
```bash
./vendor/bin/patchbot patch --patch-name=template --repository-url=ssh://git@git.example.com/repository.git
```

**Custom options**

To create the feature branch based on the branch `development`
instead of the default main branch use this command:
```bash
./vendor/bin/patchbot patch --source-branch=development --patch-name=template --repository-url=https://git.example.com/repository
```

Patchbot will use a random name for the feature branch. To use a custom name
like `feature-1337-add-license-file` for the feature branch instead run:
```bash
./vendor/bin/patchbot patch --branch-name=feature-1337-add-license-file --patch-name=template --repository-url=https://git.example.com/repository
```

It is recommended to let a CI run all tests. That's why Patchbot creates a
feature branch by default. If you want to review complex changes manually before
the commit is created, then use the `halt-before-commit` option:

```bash
./vendor/bin/patchbot patch --halt-before-commit --patch-name=template --repository-url=https://git.example.com/repository
```

To be more verbose add `-v` to each command. Add `-vvv` for debugging.
This will show all steps and commands applied by Patchbot.
The flag `--no-ansi` will remove output formation.

### Merge feature branch

✨️Patchbot intentionally creates a feature branch to apply patches.

When you reviewed the feature branch and all CI tests are successful then
you can use Patchbot again to merge the feature branch.

Example command to merge branch `bugfix-add-missing-lock-file` into
branch `main` in repository `https://git.example.com/repository`:
```bash
./vendor/bin/patchbot merge --source=bugfix-add-missing-lock-file --target=main --repository-url=https://git.example.com/repository
```

### Add a new patch

Example command to create a directory named `add-changelog-file` and
all files needed for the patch (the name is slugified automatically):
```bash
./vendor/bin/patchbot create --patch-name="Add CHANGELOG file"
```
Or copy the example folder `template` manually instead and rename it as desired.

Now replace the patch code in `patch.php` and the commit message
in `commit-message.txt`.

🛡 ️Patchbot runs the patch script isolated, as a consequence it is possible
to run the script without Patchbot.

💡 Tip: Switch to an existing projekt repository, run
`php <path to patch directory>/patch.php` and develop the patch incrementally.
When development is finished, then commit it and use Patchbot to distribute
the patch to all other repositories.

The patch code will be executed in the root directory scope of the target
repository, keep this in mind for file searches.

### Share a patch

The patches created in the patch directory are probably very specific to your
organisation or domain. So the best way to share the patches in your
organisation is to share the patch project as Git repository.

However, since a motivation for this tool was to reuse migration scripts,
you could share general-purpose scripts with others though.

One possible way is to create a GitHub Gist for a single patch.

Example command using the CLI gem [gist](https://github.com/defunkt/gist)
to upload the `template` patch:
```bash
cd patches/template/
gist -d "Patchbot Patch »template« - Just a template without changes" patch.php commit-message.txt
```

🔎 Search for [Gists with Patchbot tags](https://gist.github.com/search?l=PHP&q=%23patchbot).

### Import a shared patch

Copy & paste all files manually to import an existing patch from another source.

If the source is a Git repository then a Git clone command is sufficient.

Example command importing the
[Gist `https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745`](https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745)
as patch `add-editorconfig`:
```bash
git clone --depth=1 https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745 patches/add-editorconfig/
rm -r patches/add-editorconfig/.git
```

### Batch processing

To apply a patch to 1 or 20 repositories you may run the Patchbot script
repeatedly with different URLs. To do this with 300 repos you may want
to use the batch processing mode instead.

This mode will trigger the `patch` or `merge` command for a list of
repositories. The list is expected as CSV file named `repositories.csv`.

*repositories.csv - Example file content, with repository & branch to use*
```csv
repository-url,main-branch
https://git.example.com/projecta,main
https://git.example.com/projectb,main
https://git.example.com/projectc,development
```

The `patch` subcommand allows all options of the `patch` command, except for
`repository-url` and `source-branch`. Both are provided by the
`repositories.csv` file instead.

The following command will apply the patch script `update-changelog` to all
repository URLs in the first column of the `repositories.csv` file and create
the feature branch based on the name in the second column.

```bash
./vendor/bin/patchbot batch patch --patch-name=update-changelog
```

The `merge` subcommand also allows all options of the `merge` command,
except for `repository-url` and `target`. Both are provided by the
`repositories.csv` file instead.

The next command will merge the feature branch `feature-add-phpcs-rules`
into the branch name in the second column of the `repositories.csv` file and
in all repositories of the first column:
```bash
./vendor/bin/patchbot batch merge --source=feature-add-phpcs-rules
```

**Different branch names**

When the branch names used in the `patch` and `merge` subcommand differ,
or when you need to merge the feature branch into several stage branches
you may provide a file with all branches and pass the name of the designated
branch column as option `branch-column`.

*repositories.csv - Example file content with many branch columns*
```csv
repository-url,main,development,integration-stage,test-stage
https://git.example.com/projecta,main,development,integration,testing
https://git.example.com/projectb,main,dev,stage/integration,stage/test
https://git.example.com/projectc,live,development,stage/integration,stage/test
```

Apply the patch `rename-changelog` to the feature branch
`feature-rename-changelog`, which is based on branch name given in column
`development`:
```bash
./vendor/bin/patchbot batch patch --branch-column=development patch-name=rename-changelog branch-name=feature-rename-changelog
```
Now merge the feature branch into the branch name given in column `test-stage`
and then into the of given in column `integration-stage`:
```bash
./vendor/bin/patchbot batch merge --branch-column=test-stage source=feature-rename-changelog
./vendor/bin/patchbot batch merge --branch-column=integration-stage source=feature-rename-changelog
```

## License

GNU General Public License version 2 or later

The GNU General Public License can be found at http://www.gnu.org/copyleft/gpl.html.

## Author

Dan Untenzu (<mail@pixelbrackets.de> / [@pixelbrackets](https://pixelbrackets.de))


See [CHANGELOG.md](./CHANGELOG.md)

## Contribution

This script is Open Source, so please use, share, patch, extend or fork it.

[Contributions](./CONTRIBUTING.md) are welcome!

## Feedback

Please send some [feedback](https://pixelbrackets.de/) and share how this
package has proven useful to you or how you may help to improve it.
