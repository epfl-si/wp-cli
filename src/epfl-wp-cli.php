<?php
/**
 * EPFL WPCLI extended commands
 *
 * @author  Lucien Chaboudez <lucien.chaboudez@epfl.ch>
 * @package epfl-idevelop/wp-cli
 * @version 1.0.0
 */

namespace EPFL_WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( version_compare( PHP_VERSION, '5.5', '<' ) ) {
    WP_CLI::error( sprintf( 'This WP-CLI package requires PHP version %s or higher.', '5.5' ) );
}
if ( version_compare( WP_CLI_VERSION, '1.5.0', '<' ) ) {
    WP_CLI::error( sprintf( 'This WP-CLI package requires WP-CLI version %s or higher. Please visit %s', '1.5.0', 'https://wp-cli.org/#updating' ) );
}


define('EPFL_WP_IMAGE_PATH', '/wp/');

/* To tell if package is remote */
function is_remote_package($package)
{
    return (false !== strpos( $package, '://' ));
}

/* To tell if package is a ZIP */
function is_zip_package($package)
{
    return pathinfo( $package, PATHINFO_EXTENSION ) === 'zip' && is_file( $package );
}

/* Extracts plugin or theme name from a ZIP package (URL or local file).
 We take only what's before the first "." in the filename */
function extract_name_from_package($package)
{
    return preg_replace("/(\..+)+/", "", basename($package));
}


/* Including classes which overrides existing commands */
require_once("EPFL_Plugin_Command.php");
require_once("EPFL_Theme_Command.php");
require_once("EPFL_Core_Command.php");
