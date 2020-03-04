#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$action = $argv[1] ?? '';

$shifter = new Shifter();
$shifter->execute($action);

class Shifter
{
    const REPO_DESCRIPTION = 'Shifter Temporary Repo, can be deleted after shifting';

    /**
     * @var \Github\Client
     */
    protected $gitHub;

    /**
     * @var string|null
     */
    protected $userName = null;

    /**
     * @var string|null
     */
    protected $temporaryRepoName = null;

    /**
     * @var array
     */
    protected $temporaryRepo;

    /**
     * @var string
     */
    protected $currentBranch;

    /**
     * @var string
     */
    protected $token;

    public function __construct()
    {
        $this->gitHub = new \Github\Client();
        $this->temporaryRepoName = 'shift';
    }

    public function execute($action)
    {
        switch ($action) {
            case "push":
                $this->authenticate();
                $this->ensureTemporaryRepositoryExists();
                $this->push();
                $this->sayHowToShift();
                break;
            case "show":
                $this->authenticate();
                $this->exportPullRequest();
                break;
            case "clean":
                $this->authenticate();
                $this->removeTemporaryRepository();
                break;
            default:
                $this->showHelp();
                break;
        }
    }

    protected function showHelp()
    {
        echo 'You can use the following parameters (in the order of the lifecycle)' . PHP_EOL;

        echo PHP_EOL;
        
        echo 'shifter push (Step 1)' . PHP_EOL;
        echo '(do your shift now)' . PHP_EOL;
        echo '(merge back)' . PHP_EOL;
        echo 'shifter show > my-shift.md (show the latest merge request, dump to file)' . PHP_EOL;
        echo 'shifter clean (remove the repo from github)' . PHP_EOL;

    }

    protected function authenticate()
    {
        $tokenFile = __DIR__ . '/.github_token';

        $this->token = trim(@file_get_contents($tokenFile));

        if ( ! $this->token) {
            echo "Go to https://github.com/settings/tokens , create a token for repo access and repo_delete and put the token into $tokenFile" . PHP_EOL;
            die();
        }

        $this->gitHub->authenticate($this->token, '', \Github\Client::AUTH_HTTP_TOKEN);

        $this->userName = $this->gitHub->api('current_user')->show()['login'];
    }

    protected function ensureTemporaryRepositoryExists()
    {
        try {
            $this->temporaryRepo = $this->gitHub->api('repo')->show($this->userName, $this->temporaryRepoName);
            if ( ! $this->temporaryRepo ['private']) {
                throw \Exception('Refusing to work on public repos.');
            }
        } catch (\Github\Exception\RuntimeException $exception) {
            $this->temporaryRepo = $this->gitHub->api('repo')->create($this->temporaryRepoName,
                self::REPO_DESCRIPTION, '', false);
        }
    }

    protected function push()
    {
        $repoUrl = $this->buildRepoUrl();

        $repo = new \Cz\Git\GitRepository(getcwd());
        $this->currentBranch = $repo->getCurrentBranchName();

        try {
            $repo->removeRemote('shifter');
        } catch (\Cz\Git\GitException $e) {
            echo "Not necessary to remove remote" . PHP_EOL;
        }

        $repo->addRemote('shifter', $repoUrl);

        echo 'Pushing...';
        $repo->push('shifter', [$this->currentBranch]);
        echo 'done' . PHP_EOL;
    }

    protected function sayHowToShift()
    {
        echo "Now please go to https://laravelshift.com/shifts purchase a shift and enter the following repo name:" . PHP_EOL;
        echo $this->temporaryRepo['full_name'] . PHP_EOL;
        echo "And this branch:" . PHP_EOL;
        echo $this->currentBranch . PHP_EOL;

        echo "After you are finished, review the merge request at github, and next, merge back into your local repository using" . PHP_EOL;
        echo "git fetch shifter && git merge shifter/" . $this->currentBranch . PHP_EOL;

    }

    protected function removeTemporaryRepository()
    {
        $this->temporaryRepo = $this->gitHub->api('repo')->show($this->userName, $this->temporaryRepoName);
        if ($this->temporaryRepo['description'] != self::REPO_DESCRIPTION) {
            throw new Exception('We will work only on repos with the description "' . self::REPO_DESCRIPTION . '"');
       }

        $this->gitHub->api('repo')->remove($this->userName, $this->temporaryRepo['name']);
        echo 'GitHub temporary repository deleted' . PHP_EOL;

        $repo = new \Cz\Git\GitRepository(getcwd());

        try {
            $repo->removeRemote('shifter');
        } catch (\Cz\Git\GitException $e) {
            echo "Not necessary to remove remote" . PHP_EOL;
        }
    }

    protected function exportPullRequest()
    {
        $issues = $this->gitHub->api('issues')->all($this->userName, $this->temporaryRepoName);
        $lastIssue = array_pop($issues);

        $result = sprintf('== Pull Request %d == ', $lastIssue['number']) . PHP_EOL . PHP_EOL;

        $result .= $lastIssue['body'] . PHP_EOL . PHP_EOL;;

        $comments =  $this->gitHub->api('issue')->comments()->all($this->userName, $this->temporaryRepoName, $lastIssue['number']);

        foreach($comments as $sequence => $comment) {
            $result .= sprintf('=== Comment %d === ', $sequence + 1) . PHP_EOL . PHP_EOL;
            $result .= $comment['body'] . PHP_EOL . PHP_EOL;
        }

        echo $result;
    }

    /**
     * @return string
     */
    protected function buildRepoUrl(): string
    {
        $parts = parse_url($this->temporaryRepo['clone_url']);

        return $parts['scheme'] . '://' . $this->userName . ':' . $this->token . '@' . $parts['host'] . $parts['path'];
    }
}

