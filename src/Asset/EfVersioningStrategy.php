<?php

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class EfVersioningStrategy implements VersionStrategyInterface
{
  private $version;

  public function __construct($env, $appVersion)
  {
    if ($env === 'dev' || $env === 'test') {
      $this->version = sha1(random_bytes(10));
    }

    if ($env === 'prod') {
      $this->version = $appVersion;
    }
  }

  public function getVersion(string $path): string
  {
    return $this->version;
  }

  public function applyVersion(string $path): string
  {
    return sprintf('%s?v=%s', $path, $this->getVersion($path));
  }
}
