<?php
/**
 * @author Sebastian Eiweleit <sebastian@eiweleit.de>
 * @website https://github.com/basteyy
 * @website https://eiweleit.de
 */

declare(strict_types=1);


include 'src/Git.php';
include 'src/GitRepo.php';

$git = \Kbjr\Git\Git::open(__DIR__);

var_dump($git);