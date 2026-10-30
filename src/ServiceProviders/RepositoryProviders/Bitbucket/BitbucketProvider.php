<?php

namespace Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket;

use Pantheon\TerminusBuildTools\ServiceProviders\RepositoryProviders\Bitbucket\BitbucketProvider as BuildToolsBitbucketProvider;
use Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitProvider;

class BitbucketProvider extends BuildToolsBitbucketProvider implements GitProvider {

  public function cloneRepository($target_project, $destination) {
    // Prefer SSH when available; fall back to HTTPS with the fixed
    // token-auth username (the Atlassian email in BITBUCKET_USER
    // doesn't work for git over HTTPS with API tokens).
    exec('ssh -o BatchMode=yes -o ConnectTimeout=5 -T git@bitbucket.org 2>&1', $output, $code);
    if ($code <= 1) {
      // 0 or 1 = SSH connected (Bitbucket always returns 1 on success).
      $remote_url = "git@bitbucket.org:${target_project}.git";
      $this->exec("git clone $remote_url $destination");
    }
    else {
      $password = urlencode($this->getBitBucketPassword());
      $remote_url = "https://x-token-auth:${password}@bitbucket.org/${target_project}.git";
      $this->execWithRedaction("git clone {remote} $destination", ['remote' => $remote_url], ['remote' => $target_project]);
    }
  }

  public function closePullRequest($target_project, $id) {
    $this->logger
      ->notice("Closing PR {id} on {project}", [
        'id' => $id,
        'project' => $target_project,
      ]);

    if ($data = $this->api()
      ->request("repositories/$target_project/pullrequests/$id/decline", [], 'POST')) {
      $this->logger->notice("Pull request {id} has been closed.", ["id" => $id]);
      return $data;
    }
    $this->logger->error("Failed to close pull request {id}.", ["id" => $id]);
  }

  public function createPullRequest($target_project, $source_branch, $title, array $options = []) {
    $postData = [
      'title' => $title,
      'source' => ['branch' => ['name' => $source_branch]],
    ];

    if (!empty($options['target'])) {
      $postData['destination'] = ['branch' => ['name' => $options['target']]];
      unset($options['target']);
    }

    if (!empty($options['reviewers'])) {
      $rev = $options['reviewers'];
      $postData['reviewers'] = [];
      foreach ($rev as $reviewer) {
        $postData['reviewers'][] = ['uuid' => $reviewer];
      }
      unset($options['reviewers']);
    }

    $postData['close_source_branch'] = !empty($options['close']) ? TRUE : FALSE;
    unset($options['close']);
    $postData += array_filter($options);

    $this->logger
      ->notice("Creating pull request on {project} for {source}", [
        "project" => $target_project,
        "source" => $source_branch,
      ]);
    if ($data =
      $this->api()
        ->request("repositories/$target_project/pullrequests", $postData, 'POST')) {
      $this->logger
        ->notice("Pull request #{id} \"{title}\" created successfully: {url}", [
          "id" => $data['id'],
          "title" => $data['title'],
          "url" => $data['links']['html']['href']
        ]);
      return $data;
    }
    $this->logger
      ->error("Creating pull request failed");
    return FALSE;
  }

}
