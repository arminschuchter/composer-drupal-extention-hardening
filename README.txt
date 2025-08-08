The Drupal Extention Hardening Composer Plugin
===========================================

This is a modifed version of the drupal vendor hardening plugin
https://github.com/drupal/core-vendor-hardening

This plugin addresses the following issue for contributed modules / librarires / themes
https://www.drupal.org/project/drupal/issues/3067979

Here you can find another approach
https://drupal.stackexchange.com/questions/314813/how-can-i-remove-test-directories-when-deploying


What does it do?
----------------

It removes extraneous directories from the drupal package directory.
They're typically directories which might contain executable files, such as test
directories.

This sort of processing is required for projects that have a directory
inside the HTTP server docroot. This is a common layout for Drupal.

By default, the plugin knows how to clean up packages for Drupal extentions, so you
can require marchmol/composer-drupal-extention-hardening in your project and the rest will
happen automatically.

This plugin hardens only a limited set of extentions. See Config.php for the extentions.


How do I set it up?
-------------------

Require this Composer plugin into your project:

    composer require marchmol/composer-drupal-extention-hardening

When you install or update, this plugin will look through each package and
remove directories it knows about.

You can see the list of default package cleanups for this plugin in Config.php.
If you discover that this list needs updating, file an issue about it:
https://github.com/arminschuchter/composer-drupal-extention-hardening/issues
