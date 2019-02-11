#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$shifter = new Shifter();
$shifter->execute();

class Shifter
{
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

    public function __construct()
    {
        $this->gitHub = new \Github\Client();
        $this->temporaryRepoName = 'shifter-temporary--' . basename(getcwd());
    }

    public function execute()
    {
        try {
            $this->authenticate();
            $this->ensureTemporaryRepositoryExists();
            $this->push();
            $this->sayHowToShift();
        } catch (\Exception $exception) {
            echo sprintf('ERROR: %s @ %s:%d',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()) . PHP_EOL;
        }
    }

    protected function authenticate()
    {
        $tokenFile = __DIR__ . '/.github_token';

        $token = trim(@file_get_contents($tokenFile));

        if ( ! $token) {
            echo "Go to https://github.com/settings/tokens , create a token for repo access and repo_delete and put the token into $tokenFile" . PHP_EOL;
            die();
        }

        $this->gitHub->authenticate($token, '', \Github\Client::AUTH_HTTP_TOKEN);

        $this->userName = $this->gitHub->api('current_user')->show()['login'];
    }

    protected function ensureTemporaryRepositoryExists()
    {
        $this->temporaryRepo = $this->gitHub->api('repo')->show($this->userName, $this->temporaryRepoName);

        if ($this->temporaryRepo) {
            if ( ! $this->temporaryRepo ['private']) {
                throw \Exception('Refusing to work on public repos.');
            }
        } else {
            $this->temporaryRepo = $this->gitHub->api('repo')->create($this->temporaryRepoName,
                'Shifter Temporary Repo, can be deleted after shifting', '', false);
        }
    }

    protected function push()
    {
        $sshUrl = $this->temporaryRepo['ssh_url'];

        $repo = new \Cz\Git\GitRepository(getcwd());
        $this->currentBranch = $repo->getCurrentBranchName();

        try {
            $repo->removeRemote('shifter');
        } catch (\Cz\Git\GitException $e) {
            echo "Not necessary to remove remote" . PHP_EOL;
        }

        $repo->addRemote('shifter', $sshUrl);

        echo 'Pushing...';
        $repo->push('shifter', [$this->currentBranch]);
        echo 'done' . PHP_EOL;
    }

    protected function sayHowToShift()
    {
        echo "Now please go to https://laravelshift.com/shifts purchase a shift and enter the following repo name:" . PHP_EOL;
        echo $this->temporaryRepo['full_name'] . PHP_EOL;
        echo "And this branch:" . PHP_EOL;
        echo $this->currentBranch;
    }
}
