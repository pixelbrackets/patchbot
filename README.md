# Patchbot

[![Version](https://img.shields.io/badge/version-wip-blue.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/patchbot/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/patchbot?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/patchbot#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)

A tool to automate the distribution of patches to various Git repositories.

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

*WIP* - No package yet

Clone this repository until a package is available.

## Source

https://gitlab.com/pixelbrackets/patchbot/

## Usage

- Copy the patch template folder `./patches/template/` and rename it as desired,
  but stay in the same directory level
- Replace the commit message in `commitmessage.txt`
- Replace the patch code in `patch.php`
- The patch code will be executed in the root directory of the target
  repository, keep this in mind for file searches
- 💡 Tip: Develop the patch file in a test repository and then move it back
  to this repository
- Run the script, passing the name of the patch directory and the URL of the
  target repository

```bash
./vendor/bin/robo patch --patch-name=template --repository-url=https://gitexample.com/git/example
```

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
