<?php

namespace EPFL_WP_CLI;


/**
 * Manage theme installation with symlinks
 *
 * Parent class can be found here:
 * https://github.com/wp-cli/extension-command/blob/master/src/Theme_Command.php
 *
 */
class EPFL_Theme_Command extends \Theme_Command  {

   /**
	 * Install one or more themes. If found in WP image, a symlink is created.
	 *
	 * ## OPTIONS
	 *
	 * <theme|zip|url>...
	 * : One or more themes to install. Accepts a theme slug, the path to a local zip file, or a URL to a remote zip file.
	 *
     * [--nosymlink]
	 * : If set, plugin is installed by copying/downloading files instead of creating a symlink 
	 * if exists in WP image
     * 
	 * [--version=<version>]
	 * : If set, get that particular version from wordpress.org, instead of the
	 * stable version.
	 *
	 * [--force]
	 * : If set, the command will overwrite any installed version of the theme, without prompting
	 * for confirmation.
	 *
	 * [--activate]
	 * : If set, the theme will be activated immediately after install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the latest version from wordpress.org and activate
	 *     $ wp theme install twentysixteen --activate
	 *     Installing Twenty Sixteen (1.2)
	 *     Downloading install package from http://downloads.wordpress.org/theme/twentysixteen.1.2.zip...
	 *     Unpacking the package...
	 *     Installing the theme...
	 *     Theme installed successfully.
	 *     Activating 'twentysixteen'...
	 *     Success: Switched to 'Twenty Sixteen' theme.
	 *
	 *     # Install from a local zip file
	 *     $ wp theme install ../my-theme.zip
	 *
	 *     # Install from a remote zip file
	 *     $ wp theme install http://s3.amazonaws.com/bucketname/my-theme.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 */
    public function install( $args, $assoc_args ) {

        $no_symlink = false;
		
        if(array_key_exists('nosymlink', $assoc_args))
        {
        $no_symlink = true;

            /* We remove param to avoid errors when calling parent func */
            unset($assoc_args['nosymlink']);
        }

        /* Looping through themes to install */
        foreach ($args as $theme_name )
        {

            /* If an URL or a ZIP file has been given, we can't handle it so we call parent method */
            if(is_remote_package($theme_name) || is_zip_package($theme_name))
            {

                $extracted_theme_name = extract_name_from_package($theme_name);

                /* If theme is available in WP image 
                AND is not in the "don't use" list 
                AND we can create symlinks */
                if(!$no_symlink && path_in_image('themes', $extracted_theme_name) !== false)
                {
                    /* We change URL by theme short name so it will installed as symlink below */
                    $theme_name = $extracted_theme_name;
                }
                else
                {
                    parent::install($args, $assoc_args);
                    return;
                }
            }

            /* Looking if theme is already installed. We cannot call "parent::is_installed()" because
             it just halts process with 1 or 0 to tell theme installation status... */
            $response = \WP_CLI::launch_self( 'theme is-installed', array($theme_name), array(), false, true );

            /* If theme is not installed */
            if($response->return_code == 1)
            {

                /* If theme is available in WP image
                AND we can create symlinks */
                if(!$no_symlink && path_in_image('themes', $theme_name) !== false)
                {
                    /* Saving current working directory and changing to go into directory where WordPress is installed. 
					This will be then easier to create symlinks  */
					$current_wd = getcwd();
                    chdir(ABSPATH.'wp-content/themes/');
                    
                    /* Creating symlink to "simulate" theme installation */
                    if(symlink("../../wp/wp-content/themes/".$theme_name, $theme_name))
                    {
                        \WP_CLI::success("Symlink created for ".$theme_name);

                        /* If extra args were given (like --activate) */
                        if(sizeof($assoc_args)>0)
                        {
                            /* They cannot be handled here because when we create symlink, WP DB is not updated to
                            say "hey, theme is installed" so we will get an error when trying to do another action (ie: --activate)
                            because WordPress believe theme isn't installed */
                            \WP_CLI::warning("Extra args (starting with --) are not handled with symlinked theme, please call specific WPCLI command(s)");
                        }
                    }
                    else /* Error */
                    {
                        /* We display an error and exit */
                        \WP_CLI::error("Error creating symlink for ".$theme_name, true);
                    }

                    /* Going back to original working directory  */
					chdir($current_wd);

                }
                else /* Theme is not found in WP image  */
                {
                    \WP_CLI::log("No theme found to create symlink for ". $theme_name.". Installing it...");
                    parent::install(array($theme_name), $assoc_args);
                }

            }
            else /* Theme is already installed */
            {
                /* We call parent function to do remaining things if needed*/
                parent::install(array($theme_name), $assoc_args);
            }

        } /* END looping through given themes */
    }



    /**
	 * Delete one or more themes.
	 *
	 * Removes the theme or themes from the filesystem.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes to delete.
	 *
	 * [--all]
	 * : If set, all themes will be deleted except active theme.
	 *
	 * [--force]
	 * : To delete active theme use this.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp theme delete twentytwelve
	 *     Deleted 'twentytwelve' theme.
	 *     Success: Deleted 1 of 1 themes.
	 *
	 * @alias uninstall
	 */
    public function delete( $args )
    {
        /* Looping through themes to install */
        foreach ( $this->fetcher->get_many( $args ) as $theme )
        {
            $theme_name = $theme->get_stylesheet();

            /* Looking if theme is already installed. We cannot call "parent::is_installed()" because
             it just halts process with 1 or 0 to tell theme installation status... */
            $response = \WP_CLI::launch_self( 'theme is-installed', array($theme_name), array(), false, true );

            /* If theme is installed */
            if($response->return_code == 0)
            {
                \WP_CLI::log("Theme ".$theme_name. " is installed");

                /* If theme is active
                 NOTE: code inside "is_active_theme" was copy-pasted because we cannot call this function... because
                 it is a private function so we don't have any access to it "*/
                if($theme->get_stylesheet_directory() === get_stylesheet_directory())
                {
                    \WP_CLI::warning("Cannot remove theme ".$theme_name. " because it's the active one", true);
                    continue;
                }

                $theme_folder = ABSPATH . 'wp-content/themes/'. $theme_name;

                /* If folder is a symlink, we remove it */
                if(is_link($theme_folder))
                {
                    \WP_CLI::log("Theme ".$theme_name. " is a symlink");

                    if(unlink($theme_folder))
                    {
                        \WP_CLI::success("Symlink deleted for ".$theme_name);
                    }
                    else
                    {
                        \WP_CLI::error("Error removing symlink for ".$theme_name, true);
                    }
                }
                else /* It's a regular folder*/
                {
                    \WP_CLI::log("Theme ".$theme_name. " is a regular one");
                    /* We call parent func to do the job */
                    parent::delete(array($theme_name));
                }
            }
            else
            {
                \WP_CLI::warning("Theme ".$theme_name. " is not installed", true);
            }
        } /* END looping through given themes */

    }

}

/* We override existing commands with extended one */
\WP_CLI::add_command( 'theme', 'EPFL_WP_CLI\EPFL_Theme_Command' );