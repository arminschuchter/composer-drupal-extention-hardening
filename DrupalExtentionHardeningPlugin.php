<?php

namespace MarchMol\Composer\Plugin\DrupalExtentionHardening;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

/**
 * A Composer plugin to clean out your project's drupal core directory.
 *
 * This plugin will remove directory paths within installed packages. You might
 * use this in order to mitigate the security risks of having your drupal core
 * directory within an HTTP server's docroot.
 *
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-vendor-cleanup-composer-plugin
 *
 * @internal
 */
class DrupalExtentionHardeningPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Composer object.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * IO object.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * Configuration.
   *
   * @var \MarchMol\Composer\Plugin\DrupalCoreHardening\Config
   */
  protected $config;

  /**
   * List of projects already cleaned.
   *
   * @var string[]
   */
  protected $packagesAlreadyCleaned = [];

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;

    // Set up configuration.
    $this->config = new Config($this->composer->getPackage());
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::POST_UPDATE_CMD => 'onPostCmd',
      ScriptEvents::POST_INSTALL_CMD => 'onPostCmd',
      PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
      PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
    ];
  }

  /**
   * POST_UPDATE_CMD and POST_INSTALL_CMD event handler.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function onPostCmd(Event $event) {
    $this->cleanAllPackages();
  }

  /**
   * POST_PACKAGE_INSTALL event handler.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   The package event.
   */
  public function onPostPackageInstall(PackageEvent $event) {
    $this->cleanPackage($event->getOperation()->getPackage());
  }

  /**
   * POST_PACKAGE_UPDATE event handler.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   The package event.
   */
  public function onPostPackageUpdate(PackageEvent $event) {
    $this->cleanPackage($event->getOperation()->getTargetPackage());
  }

  /**
   * Gets a list of all installed packages from Composer.
   *
   * @return \Composer\Package\PackageInterface[]
   *   The list of installed packages.
   */
  protected function getInstalledPackages() {
    return $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
  }

  /**
   * Gets the installed path for a package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package.
   *
   * @return string
   *   Path to the install path for the package, relative to the project. This
   *   accounts for changes made by composer/installers, if any.
   */
  protected function getInstallPathForPackage(PackageInterface $package) {
    return $this->composer->getInstallationManager()->getInstallPath($package);
  }

  /**
   * Clean all configured packages.
   *
   * This applies in the context of a post-command event.
   */
  public function cleanAllPackages() {
    // Get a list of all the packages available after the update or install
    // command.
    $installed_packages = [];
    foreach ($this->getInstalledPackages() as $package) {
      // Normalize package names to lower case.
      $installed_packages[strtolower($package->getName())] = $package;
    }

    $all_cleanup_paths = $this->config->getAllCleanupPaths();

    // Get all the packages that we should clean up but haven't already.
    $cleanup_paths = array_diff_key($all_cleanup_paths, $this->packagesAlreadyCleaned);

    // Get all the packages that are installed that we should clean up.
    $packages_to_be_cleaned = array_intersect_key($cleanup_paths, $installed_packages);

    if (!$packages_to_be_cleaned) {
      $this->io->writeError('<info>Packages already clean.</info>');
      return;
    }
    $this->io->writeError('<info>Cleaning installed packages.</info>');

    foreach ($packages_to_be_cleaned as $package_name => $paths) {
      $this->cleanPathsForPackage($installed_packages[$package_name], $all_cleanup_paths[$package_name]);
    }
  }

  /**
   * Clean a single package.
   *
   * This applies in the context of a package post-install or post-update event.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to clean.
   */
  public function cleanPackage(PackageInterface $package) {
    // Normalize package names to lower case.
    $package_name = strtolower($package->getName());
    if (isset($this->packagesAlreadyCleaned[$package_name])) {
      $this->io->writeError(sprintf('%s<info>%s</info> already cleaned.', str_repeat(' ', 4), $package_name), TRUE, IOInterface::VERY_VERBOSE);
      return;
    }

    $paths_for_package = $this->config->getPathsForPackage($package_name);
    if ($paths_for_package) {
      $this->io->writeError(sprintf('%sCleaning: <info>%s</info>', str_repeat(' ', 4), $package_name));
      $this->cleanPathsForPackage($package, $paths_for_package);
    }
  }

  /**
   * Clean the installed directories for a named package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to clean.
   * @param string[] $paths_for_package
   *   List of directories in $package_name to remove
   */
  protected function cleanPathsForPackage(PackageInterface $package, $paths_for_package) {
    // Whatever happens here, this package counts as cleaned so that we don't
    // process it more than once.
    $package_name = strtolower($package->getName());
    $this->packagesAlreadyCleaned[$package_name] = TRUE;

    $package_dir = $this->getInstallPathForPackage($package);
    if (!is_dir($package_dir)) {
      return;
    }

    $this->io->writeError(sprintf('%sCleaning paths in <comment>%s</comment>', str_repeat(' ', 4), $package_name), TRUE, IOInterface::VERY_VERBOSE);
    $fs = new Filesystem();
    foreach ($paths_for_package as $cleanup_item) {
      $cleanup_path = $package_dir . '/' . $cleanup_item;
      if (!file_exists($cleanup_path)) {
        // If the package has changed or the --prefer-dist version does not
        // include the directory. This is not an error.
        $this->io->writeError(sprintf("%s<comment>Path '%s' does not exist.</comment>", str_repeat(' ', 6), $cleanup_path), TRUE, IOInterface::VERY_VERBOSE);
        continue;
      }

      if (!$fs->remove($cleanup_path)) {
        // Always display a message if this fails as it means something
        // has gone wrong. Therefore the message has to include the
        // package name as the first informational message might not
        // exist.
        $this->io->writeError(sprintf("%s<error>Failure removing path '%s'</error> in package <comment>%s</comment>.", str_repeat(' ', 6), $cleanup_item, $package_name), TRUE, IOInterface::NORMAL);
        continue;
      }

      $this->io->writeError(sprintf("%sRemoving path <info>'%s'</info>", str_repeat(' ', 4), $cleanup_item), TRUE, IOInterface::VERBOSE);
    }
  }

}
