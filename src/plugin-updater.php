<?php
/**
 * This class is responsible for updating plugins
 */
namespace MakeitWorkPress\WP_Updater;
use MakeitWorkPress\WP_Updater\Updater as Updater;
use stdClass as stdClass;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Plugin_Updater extends Updater {
    
    /**
     * Contains the information regarding the plugin
     * 
     * @var object
     * @access protected
     */
    protected $plugin;
    
    /**
     * Initializes the theme updater
     *
     * @param array $params The configuration parameters.
     */
    protected function initialize(): void {

        /**
         * Get the slug and folder for the given plugin, based on the call stack
         */
        $callstack = debug_backtrace();

        // If this file was properly called, the file it was called by will be 2 steps back and is using the add function.
        if( ! isset($callstack[2]) || $callstack[2]['function'] != 'add' ) {
            return;
        }
        
        $called         = substr($callstack[2]['file'], strpos($callstack[2]['file'], 'plugins') );
        $folders        = explode(DIRECTORY_SEPARATOR, $called);      

        // We check for a matching folder and plugin data, and then we found or plugin that called this update function
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
        $plugins = get_plugins();
        foreach( $plugins as $plugin_file => $plugin_info ) {

            if( strpos($plugin_file, $folders[1]) === false ) {
                continue;
            }

            $plugin_structure   = explode( '/', $plugin_file);
            $this->folder       = $plugin_structure[0];
            $this->slug         = str_replace('.php', '', $plugin_structure[1]);
            break;

        }

        $pluginfile  = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->folder . DIRECTORY_SEPARATOR . $this->slug . '.php';
        
        // The file does not exist, woops!
        if( ! file_exists($pluginfile) ) {
            $this->slug = null;
            return;
        }

        // Retrieve plugin information.
        $this->plugin   = get_file_data( 
            $pluginfile, 
            ['version' => 'Version', 'name' => 'Plugin Name', 'url' => 'Plugin URI', 'description' => 'Description', 'author' => 'Author', 'author_url' => 'Author URI'] 
        );
        $this->version  = $this->plugin['version'];  // Current version of the plugin

        // Ads additional information for the plugin information
        add_filter( 'plugins_api', [$this, 'plugin_info'], 20, 3 );
        
    }

    /**
     * Returns plugin info required for the view details screen
     * @param   bool|array|object   $response   Contains information concerning the update server
     * @param   string              $action     The action performed
     * @param   object              $args       Object containing various data on the plugin
     * 
     * @return  bool|array|object   $response
     */
    public function plugin_info( $response, $action, $args ) {

        // The right action should be performed
        if( $action !== 'plugin_information' ) {
            return false;
        }

        // And only for the given plugin
        if( $this->slug !== $args->slug ) {
            return $response;  
        }  
        
        // Let's draft our response
        $response                   = new stdClass();
        $response->author           = $this->plugin['author'];
        $response->author_profile   = $this->plugin['author_url'];
        $response->name             = $this->plugin['name'];
        $response->sections         = ['description' => $this->plugin['description']];
        $response->slug             = $this->slug;

        return $response;

    }
    
}