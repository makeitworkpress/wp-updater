<?php
/**
 * This class allows you to update themes and plugin through hosted versions on GitHub
 */
namespace MakeitWorkPress\WP_Updater;
use WP_Error as WP_Error;
use Plugin_Upgrader as Plugin_Upgrader;
use Theme_Upgrader as Theme_Upgrader;
use MakeitWorkPress\WP_Updater\Theme_Updater as Theme_Updater;
use MakeitWorkPress\WP_Updater\Plugin_Updater as Plugin_Updater;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Boot {

    /**
     * Holds the instance of this class
     * @access private
     */
    static private $instance = null;

    /**
     * Holds the remote source
     * @access private
     */
    private $remote_source = null;    
    
    /**
     * Contains the updaters for the registered themes and plugins
     * @access public
     */
    public $updaters = [];    
    
    /**
     * Creates the instance, so this class is only booted once
     */
    static public function instance() {

        if ( ! isset(self::$instance) ) {
            self::$instance = new self();
        }

        return self::$instance;

    }

    /**
     * Constructor for this class
     */
    public function __construct() {
        
        // This script only works in admin context
        if( ! is_admin() ) {
            return;
        }
        
        /**
         * SSL is verified by default, only supports safe updates
         */
        add_filter( 'http_request_args', [$this, 'verify_SSL'], 10, 2 );
                      
        /** 
         * Renames the source during upgrading, so it fits the structure from WordPress.
         * The name of the remote source should equal the folder name of the theme installed to properly work.
         */
        add_filter( 'upgrader_source_selection', [$this, 'source_selection'] , 10, 4 );
        
    }

    /**
     * Adds an updater, either for a theme or plugin
     *
     * @param array $config The configuration parameters to let this updater work.
     */
    public function add( Array $config = [] ) {
        
        // Default parameters 
        $config = wp_parse_args( $config, [
            'cache'     => 43200,                       // The default cache lifetime for update requests
            'request'   => ['method' => 'GET'],         // The request can be customized with custom parameters, such as a licensing token needed in the request
            'source'    => '',                          // The source, where to retrieve the update from
            'type'      => 'theme'                      // The type to update, either 'theme' or 'plugin'
        ]);

        // Check for errors
        $check = $this->checkConfig($config);
        if( is_wp_error($check) ) {
            echo $check->get_error_message();
            return;
        }     
        
        $this->remote_source = $config['source'];

        // Runs the scripts for updating a theme
        if( $config['type'] == 'theme' ) {
            $this->updaters[] = new Theme_Updater( $config );
        }
        
        // Runs the scripts for updating a plugin
        if( $config['type'] == 'plugin' ) {
            $this->updaters[] = new Plugin_Updater( $config );
        }        

    }

    /**
     * Filters our SSL verification to true
     * 
     * @param Array $args The arguments for the http request
     * @param String $url  The url for the request
     * @return Array $args The modified arguments
     */
    public function verify_SSL( $args, $url ) {
        $args[ 'sslverify' ] = true;
        return $args;
    }
    
    /**
     * Updates our source selection for the upgrader
     *
     * @param string    $source         The upgrading destination source
     * @param string    $remote_sourc   The remote source
     * @param object    $upgrader       The upgrader object
     * @param array     $hook_extra     The extra hook
     * @return string   $source         The source
     */
    public function source_selection( $source, $remote_source = NULL, $upgrader = NULL, $hook_extra = NULL ) {

        if( ! isset($source, $remote_source) || ! isset($hook_extra['theme']) ) {
            return $source;
        }

        // Renames sources for custom plugins
        if( $upgrader instanceof Plugin_Upgrader ) {
            $slug = explode('/', plugin_basename( __FILE__ ) )[0];

            // We're updating an internal plugin, so return the original source
            if( $slug !== dirname($hook_extra['plugin']) ) {
                return $source;    
            }

            $correct_source = trailingslashit( $remote_source ) . dirname( $hook_extra['plugin'] );
            
        }             

         // Renames sources for themes
        if( $upgrader instanceof Theme_Upgrader ) {
            $correct_source = trailingslashit( $remote_source . '/' . $hook_extra['theme'] );
        } 
        
        if( ! isset($correct_source) ) {
            return $source;
        }

        // We have an adjusted source       
        if( rename($source, $correct_source) ) {
            return trailingslashit( $correct_source );
        } else {
            $upgrader->skin->feedback( __("Unable to rename downloaded theme or plugin.", "wp-updater") );
            return new WP_Error();
        }

    }
    
    
    /**
     * Checks our connfigurations and see if we have everything
     * @todo Adds a sanitizer which checks urls, so that they are correct.
     *
     * @return boolean true upon success, object WP_Error upon failure
     */
    private function checkConfig($config) {
        
        if( $config['type'] !== 'theme' && $config['type'] !== 'plugin' ) {
            return new WP_Error( 'wrong', __( "Your updater type is not theme or plugin!", "wp-updater" ) );  
        }       
        
        if( empty($config['type']) ) {
            return new WP_Error( 'missing', __( "You are missing what to update, either theme or plugin.", "wp-updater" ) );  
        }      
        
        if( empty($config['source']) ) {
            return new WP_Error( 'missing', __( "You are missing the url where to update from.", "wp-updater" ) );
        }
        
        return true;
        
    }
    
}