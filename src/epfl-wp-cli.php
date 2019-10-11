<?php
/**
 * EPFL WPCLI extended commands
 *
 * @author  Lucien Chaboudez <lucien.chaboudez@epfl.ch>
 * @package epfl-idevelop/wp-cli
 * @version 1.0.3
 */

namespace EPFL_WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( version_compare( PHP_VERSION, '5.5', '<' ) ) {
  \WP_CLI::error( sprintf( 'This WP-CLI package requires PHP version %s or higher.', '5.5' ) );
}
if ( version_compare( WP_CLI_VERSION, '1.5.0', '<' ) ) {
  \WP_CLI::error( sprintf( 'This WP-CLI package requires WP-CLI version %s or higher. Please visit %s', '1.5.0', 'https://wp-cli.org/#updating' ) );
}


/**
  * To tell if package is remote
  *
  * PARAM : $package -> full path to package (URL, local path)
  */
function is_remote_package($package)
{
  return (false !== strpos( $package, '://' ));
}


/**
  * To tell if package is a ZIP
  *
  * PARAM : $package -> full path to package (URL, local path)
  */
function is_zip_package($package)
{
  return pathinfo( $package, PATHINFO_EXTENSION ) === 'zip' && is_file( $package );
}


/**
  * Extract plugin or theme name from a ZIP package (URL or local file).
  * We take only what's before the first "." in the filename
  *
  * PARAM : $package -> full path to package (URL, local path)
  */
function extract_name_from_package($package)
{
  return preg_replace("/(\..+)+/", "", basename($package));
}


/**
  * Return element path in WordPress image (if exists). Otherwise, returns FALSE
  *
  * PARAMS : $wp_content_relative_folder -> path relative to "wp-content" WordPress folder to look into for $element
  *          $element                    -> element to look into in $wp_content_relative_folder folder
  */
function path_in_image($wp_content_relative_folder, $element)
{
  $path = ABSPATH . "wp/wp-content/" . $wp_content_relative_folder . "/" . $element;
  return file_exists($path)?$path:false;
}


/* Including classes which overrides existing commands */
require_once("EPFL_Plugin_Command.php");
require_once("EPFL_Theme_Command.php");
require_once("EPFL_Core_Command.php");

/* Adding new command to install mu-plugins */
require_once("EPFL_MUPlugin_Command.php");