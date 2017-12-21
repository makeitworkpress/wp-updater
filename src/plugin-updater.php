<?php
/**
 * This class is responsible for updating plugins
 */
namespace MakeitWorkPress\WP_Updater;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Plugin_Updater extends Updater {
    
    /**
     * Contains the information regarding the plugin
     *
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
        if( ! isset($folders[0]) )
            return;
        
        $this->slug     = $folders[0];
        $file           = WP_PLUGIN_DIR . '/' . $this->slug . '/' . $this->slug . '.php';
        
        // The file does not exist, woops!
        if( ! file_exists($file) )
            return;

        // Retrieve plugin information. The assumption is that folder and plugin filename are similar.
        $this->plugin   = get_file_data( $file, array('version' => 'Version') );
        $this->version  = $this->plugin['version'];

        add_filter( 'pre_set_site_transient_update_plugins', array($this, 'checkUpdate') );
        
    }
    
}