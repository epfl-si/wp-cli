<?php

namespace EPFL_WP_CLI;

define("EPFL_WP_IMAGE_PATH", "/wp/");

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
		
		if(array_key_exists('nosymlink', $assoc_args))
		{
			$no_symlink = true;

			/* We remove param to avoid errors when calling parent func */
			unset($assoc_args['nosymlink']);
        }
        
        /* We first call parent install function to proceed to basic install */
        parent::install($args, $assoc_args);

        /* If install has been correctly done 
        AND we can use symlinks 
        AND WordPress image is present,  */
        if(is_blog_installed() && !$no_symlink && file_exists(EPFL_WP_IMAGE_PATH))
        {
            /****** 1. Symlinks creation ******/
            $this->symlink();

            /****** 2. Files modifications  ******/

            \WP_CLI::debug("---- Modifying files ----");

            $index =  ABSPATH."index.php";
            \WP_CLI::debug("Processing $index");

            $index_content = $this->read_file_content($index);
            $this->delete_or_copy_original_file_folder($index);

            $index_content = str_replace("require( dirname( __FILE__ ) . '/wp-blog-header.php' );",
                                         "require_once('/wp/wp-blog-header.php');", $index_content);
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


        foreach($to_symlink as $symlink)
        {
            $site_element       = ABSPATH. $symlink;
            $image_element      = EPFL_WP_IMAGE_PATH.$symlink;

            \WP_CLI::debug("Processing $site_element -> $image_element");

            if(!file_exists($image_element))
            {
                \WP_CLI::warning("Image element doesn't exists (".$image_element."), skipping site symlink procedure", true);

                return;
            }

            /* We rename file/folder if requested, or delete it */
            $this->delete_or_copy_original_file_folder($site_element, true);

            if(!symlink($image_element, $site_element))
            {
                \WP_CLI::error("Cannot create symlink from '".$site_element."' to '".$image_element."'", true);
            }
        }
    }
}

/* We override existing commands with extended one */
\WP_CLI::add_command( 'core', 'EPFL_WP_CLI\EPFL_Core_Command' );
