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
    'drupal/charts' => [
      'tests',
      'modules/charts_api_example',
      'modules/charts_billboard/tests',
      'modules/charts_blocks/tests',
      'modules/charts_c3/tests',
      'modules/charts_chartjs/tests',
      'modules/charts_google/tests',
      'modules/charts_highcharts/tests',
    ],
    'drupal/imce' => ['tests'],
    'drupal/juicebox' => ['tests'],
    'drupal/libraries' => ['tests'],
    'drupal/photoswipe' => [
      '.tugboat',
      'tests',
      'modules/photoswipe_dynamic_caption/tests',
    ],
    'drupal/statistics' => ['tests'],
    'drupal/time_field' => ['tests'],
    'drupal/token' => ['tests'],
    'drupal/visitors' => [
      'tests',
      'visitors_geoip/tests',
    ],
    'maxmind-db/reader' => ['ext/tests'],
    'maxstrim/juicebox' => ['full.html'],
    'mustangostang/spyc' => ['tests'],
    'npm-asset/photoswipe' => ['src'],
    'npm-asset/photoswipe-dynamic-caption-plugin' => [
      '.gitattributes',
      'index.html',
      'rollup.config.js',
    ],
    'oomphinc/composer-installers-extender' => ['tests'],
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
