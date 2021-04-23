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
- Force Git to use SSH checkouts instead of HTTP/HTTPS
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

The directory `patches` contains a collection of all “patch directories“.

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

Example command to create the feature branch based on the branch `development`
instead of the default main branch:
```bash
./vendor/bin/patchbot patch --source-branch=development --patch-name=template --repository-url=https://git.example.com/repository
```

Example command to use the custom name `feature-1337-add-license-file` for 
the feature branch instead of a random name:
```bash
./vendor/bin/patchbot patch --branch-name=feature-1337-add-license-file --patch-name=template --repository-url=https://git.example.com/repository
```

It is recommended to let a CI run all tests. That's why Patchbot creates a
feature branch by default. If you want to review complex changes manually before
the commit is created, then use the `halt-before-commit` option:

```bash
./vendor/bin/patchbot patch --halt-before-commit --patch-name=template --repository-url=https://git.example.com/repository
```

### Merge feature branch

✨️Patchbot intentionally creates a feature branch to apply patches.

When you reviewed the feature branch and all tests are successful then
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

The patches created the patch directory are probably very specific to your
organisation or domain. Since a motivation for this tool was to reuse
migration scripts, you could share general-purpose scripts with others though.

One possible way is to create a Gist for a single patch.

Example command using the CLI gem [gist](https://github.com/defunkt/gist)
to upload the `template` patch:
```bash
cd patches/template/
gist -d "Patchbot Patch »template« - Just a template without changes" patch.php commit-message.txt
```

🔎 Search for [Gists with Patchbot tags](https://gist.github.com/search?l=PHP&q=%23patchbot).

### Import a patch

Copy & paste all files manually to import an existing patch from another source.

If the source is a Git repository then a Git clone command is sufficient.

Example command importing the Gist
`https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745` as
patch `add-editorconfig`:
```bash
git clone --depth=1 https://gist.github.com/pixelbrackets/98664b79c788766e4248f16e268c5745 patches/add-editorconfig/
rm -r patches/add-editorconfig/.git
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
