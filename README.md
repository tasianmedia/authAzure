# authAzure 1.0.0-beta5
### An Azure Active Directory Extra for MODX Revolution.

[![Version](https://img.shields.io/badge/Release-v1.0.0_beta5-F78F20.svg)](https://github.com/prpgraphics/knetTheme/releases)
[![MODX Version](https://img.shields.io/badge/MODX-v2.6.x-F78F20.svg)](https://modx.com/download)
![PHP](https://img.shields.io/badge/PHP-v7.x-F78F20.svg)
[![MODX Extra by Tasian Media](https://img.shields.io/badge/Developer-Tasian_Media-F78F20.svg)](https://www.tasian.media/)

### Branches
The current 'Main' branch is '1.x'

If you have a contribution and would like to submit a Pull Request, please be sure to target the correct branch (see versioning below).

### Repository Installation
Whilst in beta:
- **DO NOT USE ON PRODUCTION WEBSITES OR MODX INSTALLS**
- Download the latest build from the [_packages](../develop/_packages/) folder and upload manually to your MODX Package Manager.
- Install via 'Git Package Management' Extra: https://github.com/theboxer/Git-Package-Management
- Fork and clone this repository using your software of choice

### Documentation
- Official Documentation: TBC
- GitHub Repository: http://github.com/tasianmedia/authazure
- Bugs & Feature Requests: http://github.com/tasianmedia/authazure/issues

### PHP Dependencies
authAzure uses [Composer](https://getcomposer.org/) as its Dependency Manager. Installation instructions: https://getcomposer.org/doc/00-intro.md

#### Commands
```
composer install
```
Installs all dependencies defined in the composer.json file.

```
composer install --prefer-dist --no-dev --no-progress --optimize-autoloader
```
Installs all dependencies defined in the composer.json file ***optimised for use in production applications***.

```
composer update
```
Updates dependencies to their latest version based on composer.json file.

```
composer dump-autoload
```
Rebuilds the autoloader file. Useful when static classmaps are updated.

```
composer outdated
```
Shows a list of installed packages that have updates available, including their current and latest versions.

### Versioning

This package follows [Semantic Versioning](http://semver.org/) rules.

- **X**.x.x = **MAJOR** versions include new functionality or features in a backwards-incompatible manner
- x.**X**.x = **MINOR** versions include new functionality or features in a backwards-compatible manner
- x.x.**X** = **PATCH** versions when we make backwards-compatible bug fixes

##### Stable Versions

Any version suffixed with a 'pl' flag indicates 'public launch' and is completely stable.

##### Alpha, Beta & Release Candidates

Any version suffixed with a 'rc' flag means it is a release candidate, and we're *trying* to avoid breaking changes as much as possible. Whilst not yet complete, it can be considered safe to use in production.

Any version suffixed with a 'beta' or 'alpha' flag means it is in active development, and you can expect breaking changes. It should not be considered for use in production.

### Please Note
authAzure is not associated with or endorsed by Microsoft Corporation.

### License
Released under the GNU General Public License; either version 2 of the License, or (at your option) any later version.
http://www.gnu.org/licenses/gpl.html

### Details
Author: David Pede (dev@tasian.media) (https://twitter.com/davepede)
Copyright: (C) 2023 Tasian Media. All rights reserved. (dev@tasian.media)
