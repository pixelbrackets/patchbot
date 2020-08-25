# Patchbot

![Logo](docs/icon.png)

[![Version](https://img.shields.io/packagist/v/pixelbrackets/patchbot.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/patchbot/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/patchbot?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)
[![Contribution](https://img.shields.io/badge/contributions_welcome-%F0%9F%94%B0-brightgreen.svg?labelColor=brightgreen&style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/-/blob/master/CONTRIBUTING.md)

A tool to automate the distribution of patches to various Git repositories.

![Screenshot](docs/screenshot.png)

## Idea

This project provides a tool to distribute changes to a several Git repositories
with as little manual work as possible.

The need for this came up when I had to apply the same manual changes to many
repositories:

- Rename files having a certain name pattern, remove a line of code
  only if a condition matches, replace a link in all documents, execute another
  tool which then changes files and so on. Nothing a plain 
  [Git patch file](https://git-scm.com/docs/git-format-patch/2.7.6) could solve,
  but something that could be automated nevertheless.
- Create a feature branch, commit all changes with a good commit message,
  push the branch, open a pull request.
- Repeat the same steps in many other repositories.

The idea is to do the changes only once and move the repetitions to a tool.
Saving time and preventing dry work.

## Requirements

- PHP
- Git

## Installation

Packagist Entry https://packagist.org/packages/pixelbrackets/patchbot/

💡 Or use the 
[skeleton package](https://packagist.org/packages/pixelbrackets/patchbot-skeleton/)
to create an example project right away.

## Source

https://gitlab.com/pixelbrackets/patchbot/

## Usage

Patchbot applies a script in a “patch directory” to a given Git repository.

This means it will clone the repository, create a feature branch,
run a given PHP patch script, commit the changes and push the branch.

The user running Patchbot needs to have access to the target repository.

The patch directory always contains a PHP script named `patch.php` and a 
commit message named `commit-message.txt`. The parent directory `patches`
contains a collection of patch directories.

Pass the name of the patch directory as `patch-name` and the Git repository as
`repository-url` to the `patchbot` script.

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

Example command applying the patch script in directory `template` to
the repository `https://git.example.com/repository`:
```bash
./vendor/bin/patchbot patch --patch-name=template --repository-url=https://git.example.com/repository
```

Example command to apply the patch based on a different branch:
```bash
./vendor/bin/patchbot patch --source-branch=development --patch-name=template --repository-url=https://git.example.com/repository
```

**Add a new patch**

- Copy the patch template folder `template` and rename it as desired
- Replace the commit message in `commit-message.txt`
- Replace the patch code in `patch.php`
- The patch code will be executed in the root directory of the target
  repository, keep this in mind for file searches
- 💡 Tip: Develop the patch file in a test repository and then move it back
  to your patch collection

## License

GNU General Public License version 2 or later

The GNU General Public License can be found at http://www.gnu.org/copyleft/gpl.html.

## Author

Dan Untenzu (<mail@pixelbrackets.de> / [@pixelbrackets](https://pixelbrackets.de))

## Changelog

See [./CHANGELOG.md](CHANGELOG.md)

## Contribution

This script is Open Source, so please use, patch, extend or fork it.

[Contributions](CONTRIBUTING.md) are welcome!
