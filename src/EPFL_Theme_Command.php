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

    var $THEME_FOLDER = "/wp/wp-content/themes/";

    /* Override existing install function*/
    public function install( $args, $assoc_args ) {

        /* Looping through themes to install */
        foreach ($args as $theme_name )
        {

            /* If an URL or a ZIP file has been given, we can't handle it so we call parent method */
            if(preg_match('/(^http|\.zip$)/', $theme_name)==1)
            {
                parent::install($args, $assoc_args);
                return;
            }

            /* Looking if theme is already installed. We cannot call "parent::is_installed()" because
             it just halts process with 1 or 0 to tell theme installation status... */
            $response = \WP_CLI::launch_self( 'theme is-installed', array($theme_name), array(), false, true );

            /* If theme is not installed */
            if($response->return_code == 1)
            {

                /* If theme is available in WP image */
                $wp_image_theme_folder = $this->THEME_FOLDER . $theme_name;
                if(file_exists($wp_image_theme_folder))
                {
                    /* Creating symlink to "simulate" theme installation */
                    if(symlink($wp_image_theme_folder, ABSPATH . 'wp-content/themes/'. $theme_name))
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

                }
                else /* Theme is not found in WP image  */
                {
                    \WP_CLI::log("No theme found to create symlink for ". $theme_name.". Installing it...");
                    parent::install(array($theme_name), $assoc_args);
                }

            }
            else /* Plugin is already installed */
            {
                /* We call parent function to do remaining things if needed*/
                parent::install(array($theme_name), $assoc_args);
            }

        } /* END looping through given themes */
    }



    /* Override existing delete function*/
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