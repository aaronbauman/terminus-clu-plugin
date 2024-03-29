<?php

namespace Pantheon\TerminusClu\ServiceProviders\RepositoryProviders;

trait CluGitTrait {

  public function createGitCluProvider($git_provider_class_or_alias) {
    if (!class_exists($git_provider_class_or_alias)) {
      switch ($git_provider_class_or_alias) {
        case 'bitbucket':
          $git_provider_class_or_alias = '\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket\BitbucketProvider';
          break;
      }
    }
    $provider = new $git_provider_class_or_alias($this->config);
    if (!$provider instanceof GitProvider) {
      throw new \Exception("Requested provider $git_provider_class_or_alias does not implement required interface Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket\GitProvider");
    }
    $provider->setLogger($this->logger);
    $this->providerManager()->credentialManager()->add($provider->credentialRequests());
    return $provider;
  }

  /**
   * @param $url
   *
   * @return \Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitProvider|void
   * @throws \Exception
   */
  public function inferGitCluProviderFromUrl($url) {
    if (strpos($url, 'bitbucket')) {
      return $this->createGitCluProvider('\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket\BitbucketProvider');
    }
  }

}
