<?php

namespace MarchMol\Composer\Plugin\DrupalExtentionHardening;

use Composer\Package\RootPackageInterface;

/**
 * Determine configuration.
 *
 * Default hardcoded configuration.
 *
 * @internal
 */
class Config {

  /**
   * The default configuration.
   *
   * @var array
   */
  protected static $defaultConfig = [
    'drupal/auto_entitylabel' => ['tests'],
    'drupal/backup_migrate' => ['tests'],
    'drupal/juicebox' => ['tests'],
    'drupal/libraries' => ['tests'],
    'drupal/time_field' => ['tests'],
    'drupal/token' => ['tests'],
  ];

  /**
   * The root package.
   *
   * @var \Composer\Package\RootPackageInterface
   */
  protected $rootPackage;

  /**
   * Configuration gleaned from the root package.
   *
   * @var array
   */
  protected $configData = [];

  /**
   * Construct a Config object.
   *
   * @param \Composer\Package\RootPackageInterface $root_package
   *   Composer package object for the root package.
   */
  public function __construct(RootPackageInterface $root_package) {
    $this->rootPackage = $root_package;
  }

  /**
   * Gets the configured list of directories to remove from the root package.
   *
   * @return array[]
   *   An array keyed by package name. Each array value is an array of paths,
   *   relative to the package.
   */
  public function getAllCleanupPaths() {
    if ($this->configData) {
      return $this->configData;
    }

    // Merge root config with defaults.
    foreach (array_change_key_case(static::$defaultConfig, CASE_LOWER) as $package => $paths) {
      $this->configData[$package] = array_merge(
        $this->configData[$package] ?? [],
        $paths);
    }
    return $this->configData;
  }

  /**
   * Get a list of paths to remove for the given package.
   *
   * @param string $package
   *   The package name.
   *
   * @return string[]
   *   Array of paths to remove, relative to the package.
   */
  public function getPathsForPackage($package) {
    $package = strtolower($package);
    $paths = $this->getAllCleanupPaths();
    return $paths[$package] ?? [];
  }

}
