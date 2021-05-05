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
     * @access protected
     */
    protected $plugin;
    
    /**
     * Initializes the theme updater
     *
     * @param array $params The configuration parameters.
     */
    protected function initialize() {
        
        // Get the slug from the plugin folder
        $folders        = explode('/', plugin_basename( __FILE__ ) );

        // Something goes wrong in the folder selection
        if( ! isset($folders[0]) ) {
            return;
        }
        
        // Set-up our slug
        $this->slug     = sanitize_title($folders[0]);
        $file           = WP_PLUGIN_DIR . '/' . $this->slug . '/' . $this->slug . '.php';        
        
        // The file does not exist, woops!
        if( ! file_exists($file) ) {
            return;
        }

        // Retrieve plugin information. The assumption is that folder and plugin filename are similar.
        $this->plugin   = get_file_data( 
            $file, 
            ['version' => 'Version', 'name' => 'Plugin Name', 'url' => 'Plugin URI', 'description' => 'Description', 'author' => 'Author', 'author_url' => 'Author URI'] 
        );
        $this->version  = $this->plugin['version'];  // Current version of the plugin

        // Ads additional information for the plugin information
        add_filter( 'plugins_api', [$this, 'pluginInfo'], 20, 3 );
        
    }

    /**
     * Returns plugin info required for the view details screen
     * @param   $response    Object Contains information concerning the update server
     * @param   $action      String The action performed
     * @param   $args        Object Object containing various data on the plugin
     */
    public function pluginInfo( $response, $action, $args ) {

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