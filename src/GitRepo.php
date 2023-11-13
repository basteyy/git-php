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

class GitRepo
{

    /** @var string $repoPath */
    protected string $repoPath;

    /** @var bool $bare */
    protected bool $bare = false;

    /** @var array $envopts */
    protected array $envopts = [];

    /** @var array $output */
    private array $output = [];

    /**
     * Constructor accepts a repository path.
     * @param string|null $repoPath
     * @param bool $createNew
     * @param bool $_init
     * @throws Exception
     */
    public function __construct(string $repoPath = null,
                                bool   $createNew = false,
                                bool   $_init = true)
    {
        if (is_string($repoPath)) {
            $this->setRepoPath($repoPath, $createNew, $_init);
        }
    }

    /**
     * Set the repository's path. Accepts the repository path
     *
     * @param string $repoPath
     * @param bool $createNew
     * @param bool $_init
     * @return void
     * @throws Exception
     */
    public function setRepoPath(string $repoPath,
                                bool   $createNew = false,
                                bool   $_init = true): void
    {
        if ($newPath = realpath($repoPath)) {
            $repoPath = $newPath;
            if (is_dir($repoPath)) {
                // Is this a work tree?
                if (file_exists($repoPath . "/.git")) {
                    $this->repoPath = $repoPath;
                    $this->bare = false;
                    // Is this a bare repo?
                } else if (is_file($repoPath . "/config")) {
                    $parse_ini = parse_ini_file($repoPath . "/config");
                    if ($parse_ini['bare']) {
                        $this->repoPath = $repoPath;
                        $this->bare = true;
                    }
                } else {
                    if ($createNew) {
                        $this->repoPath = $repoPath;
                        if ($_init) {
                            $this->run('init');
                        }
                    } else {
                        throw new Exception('"' . $repoPath . '" is not a git repository');
                    }
                }
            } else {
                throw new Exception('"' . $repoPath . '" is not a directory');
            }
        } else {
            if ($createNew) {
                if ($parent = realpath(dirname($repoPath))) {
                    mkdir($repoPath);
                    $this->repoPath = $repoPath;
                    if ($_init) {
                        $this->run('init');
                    }
                } else {
                    throw new Exception('cannot create repository in non-existent directory');
                }
            } else {
                throw new Exception('"' . $repoPath . '" does not exist');
            }
        }

    }

    /**
     * Run a git command in the git repository. Accepts a git command to run.
     * @param $command
     * @return bool
     * @throws Exception
     */
    public function run($command): bool
    {
        return $this->runCommand(Git::getBin() . " " . $command);
    }

    /**
     * Run a command in the git repository. Accepts a shell command to run
     * @param $command
     * @return bool
     * @throws Exception
     * @todo Replace with a more solid implementation
     */
    protected function runCommand($command): bool
    {
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        /* Depending on the value of variables_order, $_ENV may be empty.
         * In that case, we have to explicitly set the new variables with
         * putenv, and call proc_open with env=null to inherit the reset
         * of the system.
         *
         * This is kind of crappy because we cannot easily restore just those
         * variables afterwards.
         *
         * If $_ENV is not empty, then we can just copy it and be done with it.
         */
        if (count($_ENV) === 0) {
            $env = NULL;
            foreach ($this->envopts as $k => $v) {
                putenv(sprintf("%s=%s", $k, $v));
            }
        } else {
            $env = array_merge($_ENV, $this->envopts);
        }
        $cwd = $this->repoPath;
        $resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $this->setLastOutput($stdout);

        if (proc_close($resource) !== 0) {
            throw new Exception($stderr . PHP_EOL . $stdout);
        }

        return true;
    }

    /**
     * @param bool|string $stdout
     * @return void
     */
    private function setLastOutput(bool|string $stdout): void
    {
        $this->output[] = $stdout;
    }

    /**
     * Create a new git repository. Accepts a creation path, and, optionally, a source path.
     * @param string $repoPath
     * @param string|null $source
     * @param bool $remoteSource
     * @param string|null $reference
     * @return self
     * @throws Exception
     */
    public static function createNew(string $repoPath,
                                     string $source = null,
                                     bool   $remoteSource = false,
                                     string $reference = null): self
    {
        if (is_dir($repoPath) && file_exists($repoPath . "/.git")) {
            throw new Exception('"' . $repoPath . '" is already a git repository');
        } else {
            $repo = new self($repoPath, true, false);
            if ($source) {
                if ($remoteSource) {
                    if (isset($reference)) {
                        if (!is_dir($reference) || !is_dir($reference . '/.git')) {
                            throw new Exception('"' . $reference . '" is not a git repository. Cannot use as reference.');
                        } else if (strlen($reference)) {
                            $reference = realpath($reference);
                            $reference = "--reference $reference";
                        }
                    }
                    $repo->cloneRemote($source, $reference);
                } else {
                    $repo->cloneFrom($source);
                }
            } else {
                $repo->run('init');
            }
            return $repo;
        }
    }

    /**
     * Runs a `git clone` call to clone a remote repository into the current repository. Accepts a source url
     * @param string $source
     * @param string $reference
     * @return bool
     * @throws Exception
     */
    public function cloneRemote(string $source,
                                string $reference): bool
    {
        return $this->run("clone $reference $source " . $this->repoPath);
    }

    /**
     * Runs a `git clone` call to clone a different repository  into the current repository. Accepts a source directory
     * @param string $source
     * @return bool
     * @throws Exception
     */
    public function cloneFrom(string $source): bool
    {
        return $this->run("clone --local $source " . $this->repoPath);
    }

    /**
     * Tests if git is installed.
     * @return bool
     */
    public function testGit(): bool
    {
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $resource = proc_open(Git::getBin(), $descriptorspec, $pipes);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return proc_close($resource) !== 127;
    }

    /**
     * Runs a 'git status' call. Accept a convert to HTML bool.
     * @param bool $html
     * @return string
     * @throws Exception
     */
    public function status(bool $html = false): string
    {
        $this->run("status");
        $msg = implode("\n", $this->getLastOutput());
        return $html ? nl2br($msg) : $msg;
    }

    /**
     * @return array
     */
    public function getLastOutput(): array
    {
        return $this->output[count($this->output) + 1] ?? [];
    }

    /**
     * Runs a `git add` call. Accepts a list of files to add
     * @param string $files
     * @return bool
     * @throws Exception
     */
    public function add(string $files = "*"): bool
    {
        if (is_array($files)) {
            $files = '"' . implode('" "', $files) . '"';
        }
        return $this->run("add $files -v");
    }

    /**
     * Runs a `git rm` call. Accepts a list of files to remove.
     * @param array|string $files
     * @param bool $cached
     * @return bool
     * @throws Exception
     */
    public function rm(array|string $files = "*",
                       bool         $cached = false): bool
    {
        if (is_array($files)) {
            $files = '"' . implode('" "', $files) . '"';
        }
        return $this->run("rm " . ($cached ? '--cached ' : '') . $files);
    }

    /**
     * Runs a `git commit` call. Accepts a commit message string.
     * @param string $message
     * @param bool $commitAll
     * @return bool
     * @throws Exception
     */
    public function commit(string $message = "",
                           bool   $commitAll = true): bool
    {
        $flags = $commitAll ? '-av' : '-v';
        return $this->run("commit " . $flags . " -m " . escapeshellarg($message));
    }

    /**
     * Runs a `git clone` call to clone the current repository into a different directory. Accepts a target directory
     * @param string $target
     * @return bool
     * @throws Exception
     */
    public function cloneTo(string $target): bool
    {
        return $this->run("clone --local " . $this->repoPath . " $target");
    }

    /**
     * Runs a `git clean` call. Accepts a remove directories flag.
     * @param bool $dirs
     * @param bool $force
     * @return bool
     * @throws Exception
     */
    public function clean(bool $dirs = false,
                          bool $force = false): bool
    {
        return $this->run("clean" . (($force) ? " -f" : "") . (($dirs) ? " -d" : ""));
    }

    /**
     * Runs a `git branch` call. Accepts a name for the branch
     * @param string $branch
     * @return bool
     * @throws Exception
     */
    public function createBranch(string $branch): bool
    {
        return $this->run("branch " . escapeshellarg($branch));
    }

    /**
     * Runs a `git branch -[d|D]` call. Accepts a name for the branch.
     * @param string $branch
     * @param bool $force
     * @return bool
     * @throws Exception
     */
    public function deleteBranch(string $branch,
                                 bool   $force = false): bool
    {
        return $this->run("branch " . (($force) ? '-D' : '-d') . " $branch");
    }

    /**
     * Lists remote branches (using `git branch -r`). Also strips out the HEAD reference (e.g. "origin/HEAD -> origin/master").
     * @return string[]
     * @throws Exception
     */
    public function listRemoteBranches(): array
    {
        if (!$this->run("branch -r")) {
            return [];
        }

        $branches = $this->getLastOutput();

        foreach ($branches as $i => &$branch) {
            $branch = trim($branch);
            if ($branch == "" || strpos($branch, 'HEAD -> ') !== false) {
                unset($branches[$i]);
            }
        }

        return $branches;
    }

    /**
     * Returns name of active branch.
     * @param bool $keep_asterisk
     * @return string
     */
    public function activeBranch(bool $keep_asterisk = false): string
    {
        $branchArray = $this->listBranches(true);
        $active_branch = preg_grep("/^\*/", $branchArray);
        reset($active_branch);
        if ($keep_asterisk) {
            return current($active_branch);
        } else {
            return str_replace("* ", "", current($active_branch));
        }
    }

    /**
     * Runs a `git branch` call. Accepts a keep asterisk flag.
     * @param bool $keep_asterisk
     * @return string[]
     * @throws Exception
     */
    public function listBranches(bool $keep_asterisk = false): array
    {
        $this->run("branch");

        $branches = $this->getLastOutput();

        foreach ($branches as $i => &$branch) {
            $branch = trim($branch);
            if (!$keep_asterisk) {
                $branch = str_replace("* ", "", $branch);
            }
            if ($branch == "") {
                unset($branches[$i]);
            }
        }
        return $branches;
    }

    /**
     * Runs a `git checkout` call
     * @param $branch
     * @return bool
     * @throws Exception
     */
    public function checkout($branch): bool
    {
        return $this->run("checkout " . escapeshellarg($branch));
    }

    /**
     * Runs a `git merge` call. Accepts a name for the branch to be merged.
     * @param $branch
     * @return bool
     * @throws Exception
     */
    public function merge($branch): bool
    {
        return $this->run("merge " . escapeshellarg($branch) . " --no-ff");
    }

    /**
     * Runs a git fetch on the current branch
     * @return bool
     * @throws Exception
     */
    public function fetch(): bool
    {
        return $this->run("fetch");
    }

    /**
     * Add a new tag on the current position. Accepts the name for the tag and the message
     * @param string $tag
     * @param string|null $message
     * @return bool
     * @throws Exception
     */
    public function addTag(string $tag,
                           string $message = null): bool
    {
        if (is_null($message)) {
            $message = $tag;
        }
        return $this->run("tag -a $tag -m " . escapeshellarg($message));
    }

    /**
     * List all the available repository tags. Optionally, accept a shell wildcard pattern and return only tags matching it.
     * @param string $pattern Shell wildcard pattern to match tags against.
     * @return string[] Available repository tags.
     * @throws Exception
     */
    public function listTags(string $pattern): array
    {
        $this->run("tag -l $pattern");

        $tags = $this->getLastOutput();
        foreach ($tags as $i => &$tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                unset($tags[$i]);
            }
        }

        return $tags;
    }

    /**
     * Push specific branch (or all branches) to a remote. Accepts the name of the remote and local branch.
     *  If omitted, the command will be "git push", and therefore will take
     *  on the behavior of your "push.defualt" configuration setting.
     * @param string $remote
     * @param string $branch
     * @return bool
     * @throws Exception
     */
    public function push(string $remote,
                         string $branch): bool
    {
        //--tags removed since this was preventing branches from being pushed (only tags were)
        return $this->run('push ' . $remote . ($branch ? ' ' . $branch : ''));
    }

    /**
     * Pull specific branch from remote. Accepts the name of the remote and local branch.
     *  If omitted, the command will be "git pull", and therefore will take on the
     *  behavior as-configured in your clone / environment.
     * @param string $remote
     * @param string|null $branch
     * @return bool
     * @throws Exception
     */
    public function pull(string $remote,
                         string $branch = null): bool
    {
        return $this->run('pull ' . $remote . ($branch ? ' ' . $branch : ''));
    }

    /**
     * List log entries.
     * @param bool $format
     * @param bool $fullDiff
     * @param string|null $filepath
     * @param bool $follow
     * @param bool $return_string
     * @return string|array
     * @throws Exception
     */
    public function log(bool   $format = false,
                        bool   $fullDiff = false,
                        string $filepath = null,
                        bool   $follow = false,
                        bool   $return_string = true): string|array
    {
        $diff = "";

        if ($fullDiff) {
            $diff = "--full-diff -p ";
        }

        if ($follow) {
            // Can't use full-diff with follow
            $diff = "--follow -- ";
        }

        if ($format) {
            $this->run('log ' . $diff . $filepath ?? '');
        } else {
            $this->run('log --pretty=format:"' . $format . '" ' . $diff . $filepath ?? '');
        }

        if ($return_string) {
            return implode(PHP_EOL, $this->getLastOutput());
        }
        return $this->getLastOutput();

    }

    /**
     * Sets the project description.
     * @param $new
     * @return void
     * @throws Exception
     */
    public function setDescription($new): void
    {
        $path = $this->gitDirectoryPath();
        file_put_contents($path . "/description", $new);
    }

    /**
     * Get the path to the git repo directory (eg. the ".git" directory)
     * @return string
     * @throws Exception
     */
    public function gitDirectoryPath(): string
    {
        if ($this->bare) {
            return $this->repoPath;
        } else if (is_dir($this->repoPath . "/.git")) {
            return $this->repoPath . "/.git";
        } else if (is_file($this->repoPath . "/.git")) {
            $gitFile = file_get_contents($this->repoPath . "/.git");
            if (mb_ereg("^gitdir: (.+)$", $gitFile, $matches)) {
                if ($matches[1]) {
                    $relGitPath = $matches[1];
                    return $this->repoPath . "/" . $relGitPath;
                }
            }
        }
        throw new Exception('Could not find git dir for ' . $this->repoPath . '.');
    }

    /**
     * Gets the project description.
     * @return string
     * @throws Exception
     */
    public function getDescription(): string
    {
        $path = $this->gitDirectoryPath();
        return file_get_contents($path . "/description") ?? '';
    }

    /**
     * Sets custom environment options for calling Git
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setenv(string $key, string $value): void
    {
        $this->envopts[$key] = $value;
    }
}
