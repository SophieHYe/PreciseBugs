# VSCode PHP Mess Detector

[![Build Status](https://travis-ci.org/sandhje/vscode-phpmd.svg?branch=master)](https://travis-ci.org/sandhje/vscode-phpmd)
[![codecov](https://codecov.io/gh/sandhje/vscode-phpmd/branch/master/graph/badge.svg)](https://codecov.io/gh/sandhje/vscode-phpmd)
[![Join the chat at https://gitter.im/sandhje/vscode-phpmd](https://badges.gitter.im/sandhje/vscode-phpmd.svg)](https://gitter.im/sandhje/vscode-phpmd?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Features

* Analyze your PHP source code on save with PHP mess detector
* No additional setup required if PHP is installed on your system
* Customize which PHPMD rules are used
* Supports custom PHPMD ruleset files

## Installation

To install the extension: Press `F1`, type `ext install vscode-phpmd`

### Using the built-in PHPMD PHAR

To use the built-in PHP mess detector you need to have PHP in your PATH. To test, open a shell or command window and type `php -v`, this should return "PHP x.x.x ...". If PHP is in your PATH you can directly use this extension, no further setup is required.

### Using a custom PHPMD PHAR or executable

If you want to customize the default PHP mess detector command, e.g. you have PHP mess detector globally installed through composer or have PHP available on a different location, you can customize the command with the `command` setting. 

## Configuration

The following configuration options are available:

### phpmd.command: 
Customize the PHP mess detector command. If left empty the built-in PHPMD PHAR archive will be executed and PHP needs to be available on your PATH. If you want to use a different PHPMD PHAR you can customize the command here. 

#### Examples:
To use PHPMD installed globally with composer on a windows machine set this setting to: 
```
"phpmd.command": "C:/Users/{USER}/AppData/Roaming/Composer/vendor/bin/phpmd.bat"
```

Or to use your own PHPMD PHAR on a custom location: 
```
"phpmd.command": "php C:/path/to/phpmd.phar"`
```

### phpmd.rules: 

Customize the PHPMD ruleset files used. This option can also take the path to a custom [PHPMD ruleset file](https://phpmd.org/documentation/creating-a-ruleset.html). Use VS Code's workspace settings to control the rules or ruleset files per workspace. When setting a path to a ruleset file, and the path starts with "~/" this will be replaced with the OS homedir. The string "${workspaceFolder}" in a path to a ruleset file will be replaced with an absolute path to the folder in the workspace which relates to the file that is being validated. Refer to [PHPMD's documentation](https://phpmd.org/documentation/index.html) for more information on the ruleset parameter.

#### Examples:
To use only the cleancode ruleset and skip all the others: 
```
"phpmd.rules": "cleancode"
```

Pass a comma seperated list of rulesets: 
```
"phpmd.rules": "cleancode,codesize"
```

Pass the path to a ruleset file: 
```
"phpmd.rules": "C:/path/to/phpmd_config.xml"
```

Pass the path to a ruleset file located in the home directory: 
```
"phpmd.rules": "~/phpmd_config.xml"
```

Pass the path to a ruleset file located in the workspace folder: 
```
"phpmd.rules": "${workspaceFolder}/phpmd_config.xml"
```

### phpmd.verbose: 
Turn verbose logging on or off. All log entries can be viewed VS Code's output panel. Generally this can be turned off (default) unless you need to troubleshoot problems.

#### Examples:
To enable verbose logging: 
```
"phpmd.verbose": true
```

## System requirements
* PHP_Depend >= 2.0.0
* PHP >= 5.3.9
* [PHP XML extension](https://www.php.net/manual/en/simplexml.installation.php)

## Troubleshooting
* Turn on verbose logging through settings and check output
* Ask a question on [gitter](https://gitter.im/sandhje/vscode-phpmd)
* Found a bug? [file an issue](https://github.com/sandhje/vscode-phpmd/issues) (include the logs)

## Contributing

If you found a bug or can help with adding a new feature to this extension you can submit any code through a pull request. The requirements for a pull request to be accepted are:

* Add unit tests for all new code (code coverage must not drop)
* Add JSDoc comments to all "things"
* Make sure there are no TSLint violations (see tslint.json)

Before contributing also make sure you are familiar with VSCode's [language server development](https://code.visualstudio.com/docs/extensions/example-language-server)

Install all dependencies with [yarn](https://yarnpkg.com/lang/en/)

## History

See client/CHANGELOG.md

## Acknowledgements

* The people behind [PHPMD](https://phpmd.org/people-behind.html)
* The Microsoft VSCode team for [VSCode](https://code.visualstudio.com/) and [vscode-languageserver-node](https://github.com/Microsoft/vscode-languageserver-node).
* Quentin Dreyer for his OS homedir replacement solution (https://github.com/qkdreyer)
* Shane Smith for his spelling fixes (https://github.com/shane-smith)
