<?php

namespace EPFL_WP_CLI;

define("EPFL_WP_IMAGE_PATH", "/wp/");
define('EPFL_WP_DEFAULT_VERSION', '4');

/**
 * Manage WordPress Core installation with symlinks
 *
 * Parent class can be found here:
 * https://github.com/wp-cli/core-command/blob/master/src/Core_Command.php
 *
 */
class EPFL_Core_Command extends \Core_Command {
    /* If enabled, we'll keep a copy of original files/folders */
    var $DEBUG = false;
    var $ORIGINAL_FILES_FOLDERS_SUFFIX = "_old";

    /* Copy or rename an original file/folder to keep it as "backup" before modifying or symlinking it */
    private function delete_or_copy_original_file_folder($source, $rename=false)
    {

        /* If we're debugging, we copy/rename files */
        if($this->DEBUG)
        {

            $target = $source.$this->ORIGINAL_FILES_FOLDERS_SUFFIX;

            if(file_exists($target))
            {
                \WP_CLI::error("Cannot rename/copy because target ($target) already exists", true);
            }


            /* If we have to rename instead of copy */
            if($rename)
            {
                if(!rename($source, $target))
                {
                    \WP_CLI::error("Cannot rename '".$source."' to '".$target."'", true);
                }
            }
            else /* We just have to copy file */
            {
                if(!copy($source, $target))
                {
                    \WP_CLI::error("Cannot copy '".$source."' to '".$target."'", true);
                }
            }
        }
        else /* We're not debugging so we delete the file */
        {
            $this->delete_file_folder($source);
        }
    }


    /* Read a file content and returns it. Also handle errors */
    private function read_file_content($file)
    {
        if(($content = file_get_contents($file))===false)
        {
            \WP_CLI::error("Cannot read '".$file."' file content", true);
        }

        return $content;
    }

    /* Update file content and handles errors */
    private function update_file_content($file, $content)
    {
        if(file_put_contents($file, $content)===false)
        {
            \WP_CLI::error("Cannot update '".$file."' file content", true);
        }
    }

    /* Deletes a file or folder and handles errors */
    private function delete_file_folder($path)
    {
        /* If we have to delete a file */
        if (!is_dir($path))
        {
            if(!unlink($path))
            {
                \WP_CLI::warning("Cannot delete '".$path."'", true);
            }
        }
        else /* We have to delete a directory */
        {
            $dir_handle = opendir($path);
            if (!$dir_handle)
            {
                \WP_CLI::error("Cannot read directory '".$path."'", true);
            }

            while($file = readdir($dir_handle))
            {
               if ($file != "." && $file != "..")
               {
                    if (!is_dir($path."/".$file))
                    {
                         unlink($path."/".$file);
                    }
                    else
                    {
                         $this->delete_file_folder($path.'/'.$file);
                    }
                }
            }
            closedir($dir_handle);
            rmdir($path);
        }
    }


    /**
	 * Runs the standard WordPress installation process.
	 *
	 * Creates the WordPress tables in the database using the URL, title, and
	 * default admin user details provided. Performs the famous 5 minute install
	 * in seconds or less.
	 *
	 * Note: if you've installed WordPress in a subdirectory, then you'll need
	 * to `wp option update siteurl` after `wp core install`. For instance, if
	 * WordPress is installed in the `/wp` directory and your domain is example.com,
	 * then you'll need to run `wp option update siteurl http://example.com/wp` for
	 * your WordPress installation to function properly.
	 *
	 * Note: When using custom user tables (e.g. `CUSTOM_USER_TABLE`), the admin
	 * email and password are ignored if the user_login already exists. If the
	 * user_login doesn't exist, a new user will be created.
	 *
	 * ## OPTIONS
	 *
     * [--wpversion=<version>]
     * : WordPress version to install, by default, it's 4.x.x defined in image. THIS OPTION WON'T BE USED IF --nosymlink IS GIVEN
     * 
     * [--nosymlink]
	 * : If set, plugin is installed by copying/downloading files instead of creating a symlink 
	 * if exists in WP image
     * 
	 * --url=<url>
	 * : The address of the new site.
	 *
	 * --title=<site-title>
	 * : The title of the new site.
	 *
	 * --admin_user=<username>
	 * : The name of the admin user.
	 *
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * [--skip-email]
	 * : Don't send an email notification to the new admin user.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install WordPress in 5 seconds
	 *     $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_password=strongpassword --admin_email=info@example.com
	 *     Success: WordPress installed successfully.
	 *
	 *     # Install WordPress without disclosing admin_password to bash history
	 *     $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_email=info@example.com --prompt=admin_password < admin_password.txt
	 */
    public function install( $args, $assoc_args ) {

        $no_symlink = false;
        
        /* Normal install, without symlinks */
        if(array_key_exists('nosymlink', $assoc_args))
        {
            $no_symlink = true;

            /* We remove param to avoid errors when calling parent func */
            unset($assoc_args['nosymlink']);
        }
        else /* Symlinked install, so we can choose version */
        {

            if(array_key_exists('wpversion', $assoc_args))
            {
                /* Version will be like X.X.X */
                $version = $assoc_args['wpversion'];
                
                /* We remove param to avoid errors when calling parent func */
                unset($assoc_args['wpversion']);
            }
            else
            {
                $version = EPFL_WP_DEFAULT_VERSION;
            }

            /* it will look like /wp/5.2.2  */
            $path_to_version = EPFL_WP_IMAGE_PATH.$version;

            /* We first check that wished WordPress version is present in image */
            if(!file_exists($path_to_version))
            {
                \WP_CLI::error("Requested WordPress version (".$version.") is not in image");
            }

            /* If we have a link named with the major version number pointing on the full asked version,
            ex: 5 -> 5.2.2
            In this case we will use 5 instead of 5.2.2 */
            $path_to_major_version = EPFL_WP_IMAGE_PATH.$version[0];
            if(file_exists($path_to_major_version) && readlink($path_to_major_version)==$version)
            {
                $path_to_version = $path_to_major_version;
            }
        }
        
        /* Then, we call parent install function to proceed to basic install */
        parent::install($args, $assoc_args);

        /* If install has been correctly done 
        AND we can use symlinks 
        AND WordPress image is present,  */
        if(is_blog_installed() && !$no_symlink)
        {
            /****** 1. Symlinks creation ******/
            $this->symlink(array(), array('path_to_version' => $path_to_version));

            /****** 2. Files modifications  ******/

            \WP_CLI::debug("---- Modifying files ----");

            $index =  ABSPATH."index.php";
            \WP_CLI::debug("Processing $index");

            $index_content = $this->read_file_content($index);
            $this->delete_or_copy_original_file_folder($index);

            $index_content = str_replace("require( dirname( __FILE__ ) . '/wp-blog-header.php' );",
                                         "require_once('wp/wp-blog-header.php');", $index_content);
            $this->update_file_content($index, $index_content);


            $wp_config = ABSPATH."wp-config.php";
            \WP_CLI::debug("Processing $wp_config");

            $wp_config_content = $this->read_file_content($wp_config);
            $this->delete_or_copy_original_file_folder($wp_config);
            $wp_config_content = str_replace("table_prefix = 'wp_';",
                                             "table_prefix = 'wp_';\n".
                                             "define('WP_CONTENT_DIR', '".ABSPATH."wp-content');", $wp_config_content);
            $this->update_file_content($wp_config, $wp_config_content);
        }
    }

    /**
     * Make symlinks to /wp if available.
     *
     * Note: If the current directory doesn't contain a wp-config.php file, then --path flag must be used.
     *
     * ## EXAMPLES
     *
     *     wp --path=$PWD core symlink
     *
     * @when before_wp_load
     */
    public function symlink ($args = array(), $assoc_args = array()) {
        $to_symlink = array(
                /* Files */
                "wp-cron.php",
                "wp-load.php",
                "wp-login.php",
                "wp-settings.php",
                /* Folders*/
                "wp-includes"
                );


        \WP_CLI::debug("---- Creating symlinks ----");

        /* We first create symlink to access desired version */
        if(!$this->ensure_symlink($assoc_args['path_to_version'], ABSPATH."wp",  /* $remove_if_needed = */ TRUE))
        {
            \WP_CLI::error("Cannot create symlink on WP image '".$assoc_args['path_to_version']."'", true);
        }

        /* Saving current working directory and changing to go into directory where WordPress is installed. 
        This will be then easier to create symlinks  */
        $current_wd = getcwd();
        chdir(ABSPATH);

        foreach($to_symlink as $symlink)
        {
            $site_element       =  $symlink;
            $image_element      = "wp/".$symlink;

            \WP_CLI::debug("Processing $site_element -> $image_element");

            if(!file_exists($image_element))
            {
                \WP_CLI::warning("Image element doesn't exist (".$image_element."), skipping site symlink procedure", true);

                return;
            }

            if(!$this->ensure_symlink($image_element, $site_element, /* $remove_if_needed = */ TRUE))
            {
                \WP_CLI::error("Cannot create symlink from '".$site_element."' to '".$image_element."'", true);
            }
        }
        /* Going back to original working directory  */
        chdir($current_wd);
    }

    private function ensure_symlink ($from, $to, $remove_if_needed=FALSE) {
        if (@readlink($to) === $from) {
            \WP_CLI::debug("$to is already a symlink to $from");
            return TRUE;
        }
        if ($remove_if_needed) {
            $operation = null;
            if (@readlink($to)) {
                $operation = "unlink() symlink $to";
                $success = unlink($to);
            } elseif (is_dir($to)) {
                $operation = "rmdir($to)";
                $success = rmdir($to);
            } else if (is_file($to)) {
                $operation = "unlink($to)";
                $success = unlink($to);
            } else if (file_exists($to)) {
                \WP_CLI::warning("Unable to determine type of $to: " .
                                 posix_strerror(posix_get_last_error()));
                return FALSE;
            }
            if ($operation) {
                if ($success) {
                    \WP_CLI::debug($operation);
                } else {
                    \WP_CLI::warning("Cannot $operation: " . posix_strerror(posix_get_last_error()));
                    return FALSE;
                }
            }
        }
        \WP_CLI::debug("symlink($from, $to)");
        if (! symlink($from, $to)) {
            \WP_CLI::warning("Cannot symlink($from, $to): " . posix_strerror(posix_get_last_error()));
            return FALSE;
        }
        return TRUE;
    }
}

/* We override existing commands with extended one */
\WP_CLI::add_command( 'core', 'EPFL_WP_CLI\EPFL_Core_Command' );
