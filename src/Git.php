<?php
/**
 * Fork of Git.php by basteyy
 *
 * @author James Brumond
 * @copyright Copyright 2013 James Brumond
 * @website http://github.com/kbjr/Git.php
 * @license MIT
 * @website http://github.com/basteyy/git-php
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Kbjr\Git;

use Exception;

class Git 
{    
    /** @var string $bin `git` executable location */
    protected static string $bin = '/usr/bin/git';

	/**
     * Constructor
     */
	function __construct()
	{
		if (file_exists('/usr/bin/git')) {
			self::$bin = '/usr/bin/git';
		} else {
			self::$bin = 'git';
		}
	}

    /**
     * Gets `git` executable path
     * @return string
     */
    public static function getBin(): string
    {
        return self::$bin;
    }

    /**
     * Sets `git` executable path
     * @param $path
     * @return void
     */
    public static function setBin($path): void
    {
        self::$bin = $path;
    }

    /**
     * Sets `git` executable path to Windows mode
     * @return void
     */
    public static function windowsMode(): void
    {
        self::setBin('git');
    }

    /**
     * Create a new `git` repository. Accepts a creation path, and, optionally, a source path.
     * @param string $repoPath
     * @param string|null $source
     * @return GitRepo
     * @throws Exception
     */
    public static function create(string $repoPath,
                                  string $source = null): GitRepo
    {
        return GitRepo::createNew($repoPath, $source);
    }

    /**
     * Open an existing `git` repository. Accepts a repository path.
     * @param $repoPath
     * @return GitRepo
     */
    public static function open($repoPath): GitRepo
    {
        return new GitRepo($repoPath);
    }

    /**
     * Clones a remote repo into a directory and then returns a GitRepo object for the newly created local repo. Accepts a creation path and a remote to clone from.
     * @param $repoPath
     * @param $remote
     * @param $reference
     * @return GitRepo
     * @throws Exception
     */
    public static function cloneRemote($repoPath,
                                       $remote,
                                       $reference = null): GitRepo
    {
        return GitRepo::createNew($repoPath, $remote, false, $reference);
    }

    /**
     * Checks if a variable is an instance of GitRepo. Accepts a variable to check.
     * @param object $var
     * @return bool
     */
    public static function isRepo(object $var): bool
    {
        return (get_class($var) == GitRepo::class);
    }
}
