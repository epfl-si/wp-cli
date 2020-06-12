<?php

namespace EPFL_WP_CLI;

/**
 * Manage mu-plugin installation with symlinks
 *
 * This class is not a child of Plugin_Command like EPFL_Plugin_Command because we don't need elements from parent
 * class to do the job
 *
 */
class EPFL_MUPlugin_Command  {


    private function recurse_copy($src, $dst)
    {

        if(!file_exists($dst))
        {
            if(!mkdir($dst)) return false;
        }

        $dir = opendir($src);
        while(false !== ( $file = readdir($dir)) )
        {
            if(($file != '.' ) && ( $file != '..' ))
            {
                if(is_dir($src . '/' . $file) )
                {
                    $this->recurse_copy($src . '/' . $file, $dst . '/' . $file);
                }
                else
                {
                    if(!copy($src . '/' . $file,$dst . '/' . $file)) return false;
                }
            }
        }
        closedir($dir);

        return true;
    }

    /**
     * Install a mu-plugin element (file/folder). Create a symlink if in WordPress image or copy from source
     *
     * Params  : $file_or_folder_path -> path to a file/folder we want to install (create symlink or copy it)
     *           $no_symlink -> true|false to tell if we can use symlinks or not
     *
     *
     * Returns : TRUE  => Symlink created (or already existing)
     *           FALSE => Symlink not created (because not in image)
     *
     *           In case of error, func will exit
     *
     */
    private function install_element($file_or_folder_path, $no_symlink)
    {
        $file_or_folder = basename($file_or_folder_path);

        /* Path to MU-plugin in current WordPress */
        $target_file_or_folder_path = ABSPATH . 'wp-content/mu-plugins/'. $file_or_folder;

        /* If mu-plugin file/folder is not installed */
        if(!file_exists($target_file_or_folder_path))
        {

            /* If we can use symlinks AND
             file/folder is available in WP image */
            if(!$no_symlink && path_in_image('mu-plugins', $file_or_folder)!==false)
            {
                /* Saving current working directory and changing to go into directory where WordPress is installed. 
                This will be then easier to create symlinks  */
                $current_wd = getcwd();
                chdir(ABSPATH.'wp-content/mu-plugins/');

                /* Creating symlink to "simulate" mu-plugin installation */
                if(!symlink("../../wp/wp-content/mu-plugins/".$file_or_folder, $file_or_folder))
                {
                    \WP_CLI::error("Error creating symlink for ".$file_or_folder, true);
                }

                \WP_CLI::success("Symlink created for ".$file_or_folder);

                /* Going back to original working directory  */
                chdir($current_wd);

            }
            else /* file/folder is not part of WP image */
            {
                /* Element is a file */
                if(is_file($file_or_folder_path))
                {
                    /* If we arrive here, it means that mu-plugin PHP file is not part of the image. So we just copy
                    source file to mu-plugin directory */
                    if(!copy($file_or_folder_path, $target_file_or_folder_path))
                    {
                        \WP_CLI::error("Error copying mu-plugin file (".$file_or_folder_path. " to ".$target_file_or_folder_path.")", true);
                    }
                }
                else /* Element is a folder */
                {
                    if(!$this->recurse_copy($file_or_folder_path, $target_file_or_folder_path))
                    {
                        \WP_CLI::error("Error copying mu-plugin folder (".$file_or_folder_path. " to ".$target_file_or_folder_path.")", true);
                    }

                }
            }
        }
        else /* Already installed */
        {
            \WP_CLI::log("MU-plugin element already installed '".$file_or_folder."'");
        }
    }


    /**
	 * Install one or more mu-plugins.
	 *
	 * ## OPTIONS
	 *
	 * <file>...
	 * : One or more plugins to install. Accepts a path to a mu-plugin file (file with all mu-plugin code or
	 * loader for others files). In case of a loader, use --folder parameter too
	 *
     * [--nosymlink]
	 * : If set, plugin is installed by copying/downloading files instead of creating a symlink 
	 * if exists in WP image
     * 
	 * [--folder=<folder>]
	 * : Use this if mu-plugin contains a PHP file that "loads" several resources presents in a folder. Path to folder
	 * must be given
	 *
	 */
    public function install( $args, $assoc_args ) {

        $no_symlink = false;
		
        if(array_key_exists('nosymlink', $assoc_args))
        {
            $no_symlink = true;

            /* We remove param to avoid errors when calling parent func */
            unset($assoc_args['nosymlink']);
        }
        
        /* Looping through mu-plugins to install */
        foreach($args as $mu_plugin_file_path)
        {
            /* Installing file */
            $this->install_element($mu_plugin_file_path, $no_symlink);

            /* If there's also a folder to handle, */
            if(array_key_exists('folder', $assoc_args) && $assoc_args['folder'] != "")
            {
                /* We install folder for mu-plugin */
                $this->install_element($assoc_args['folder'], $no_symlink);
            }

        } /* END looping through given mu-plugins */
    }


}

/* We add this command to existing ones */
\WP_CLI::add_command( 'mu-plugin', 'EPFL_WP_CLI\EPFL_MUPlugin_Command' );
