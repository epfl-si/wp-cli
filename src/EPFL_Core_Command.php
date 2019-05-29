<?php

namespace EPFL_WP_CLI;


/**
 * Manage WordPress Core installation with symlinks
 *
 * Parent class can be found here:
 * https://github.com/wp-cli/core-command/blob/master/src/Core_Command.php
 *
 */
class EPFL_Core_Command extends \Core_Command   {


    /* Override existing install function*/
    public function install( $args, $assoc_args ) {

        /* We first call parent install function to proceed to basic install */
        parent::install($args, $assoc_args);

        /* If install has been correctly done  */
        if(is_blog_installed())
        {
            $to_symlink = array(
                /* Files */
                "wp-cron.php",
                "wp-load.php",
                "wp-login.php",
                "wp-settings.php",
                /* Folders*/
                "wp-includes"
                );


            foreach($to_symlink as $symlink)
            {
                $site_element       = ABSPATH. "/". $symlink;
                $site_element_old   = $site_element."_old";
                $image_element      = "/wp/".$symlink;

                if(!file_exists($image_element))
                {
                    \WP_CLI::warning("Image element doesn't exists (".$image_element."), skipping symlink creation", true);
                    continue;
                }

                if(!rename($site_element, $site_element_old))
                {
                    \WP_CLI::error("Cannot rename '".$site_element."' to '".$site_element_old."'", true);
                }

                if(!link($image_element, $site_element))
                {
                    \WP_CLI::error("Cannot create symlink from '".$site_element."' to '".$image_element."'", true);
                }

            }

        }
    }
}

/* We override existing commands with extended one */
\WP_CLI::add_command( 'core', 'EPFL_WP_CLI\EPFL_Core_Command' );