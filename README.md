# git-php

`basteyy/git-php` is a fork of `kbjr/Git.php` from James Brumond. See the original github page: https://github.com/kbjr/Git.php


## Description

A PHP git repository control library. Allows the running of any git command from a PHP class. Runs git commands using `proc_open`, not `exec` or the type, therefore it can run in PHP safe mode.

## Requirements

A system with [git](http://git-scm.com/) installed

## Install

Use composer tp install the package:

```bash
composer require basteyy/git-php
```

## Basic Use

```php
require dirname(__DIR__) . '/vendor/autoload.php';

use Kbjr\Git\Git;

$repo = Git::open('/path/to/repo');  // -or- Git::create('/path/to/repo')

$repo->add('.');
$repo->commit('Some commit message');
$repo->push('origin', 'master');
```

---


