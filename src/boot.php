<?php
/**
 * This class allows you to update themes and plugin through hosted versions on GitHub
 */
namespace MakeitWorkPress\WP_Updater;
use WP_Error as WP_Error;
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
     * Contains the updaters for the registered themes and plugins
     * @access public
     */
    public $updaters = [];
    
    /**
     * Creates the instance, so this class is only booted once
     */
    static public function instance(): Boot {

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
         * Renames the source during upgrading, so it fits the structure from WordPress
         */
        add_filter( 'upgrader_source_selection', [$this, 'source_selection'] , 10, 4 );
        
    }

    /**
     * Adds an updater, either for a theme or plugin
     *
     * @param array $config The configuration parameters to let this updater work.
     */
    public function add( array $config = [] ): void {
        
        // Default parameters 
        $config = wp_parse_args( $config, [
            'cache'     => 43200,                       // The default cache lifetime for update requests
            'request'   => ['method' => 'GET'],         // The request can be customized with custom parameters, such as a licensing token needed in the request
            'source'    => '',                          // The source, where to retrieve the update from
            'type'      => 'theme'                      // The type to update, either 'theme' or 'plugin'
        ]);

        // Check for errors
        $check = $this->check_config($config);
        
        if( is_wp_error($check) ) {
            echo $check->get_error_message();
            return;
        }        

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
     * @param array $args The arguments for the http request
     * @param string $url  The url for the request
     * @return array $args The modified arguments
     */
    public function verify_SSL( array $args, string $url ): array {
        $args[ 'sslverify' ] = true;
        return $args;
    }
    
    /**
     * Updates our source selection for the upgrader
     *
     * @param string                        $source         The upgrading destination source
     * @param string                        $remote_sourc   The remote source
     * @param WP_Upgrader|Plugin_Upgrader   $upgrader       The upgrader object
     * @param array                         $hook_extra     The extra hook
     * @return string|WP_Error              $source         The source
     */
    public function source_selection( string $source, string $remote_source = NULL, $upgrader = NULL, array $hook_extra = NULL ) {

        if( isset($source, $remote_source) ) {

            // Retrieves the source for themes
            if( isset($upgrader->skin->theme_info->stylesheet) && $upgrader->skin->theme_info->stylesheet ) {
                $correct_source = trailingslashit( $remote_source . '/' . $upgrader->skin->theme_info->stylesheet );
            }

            // Retrieves for plugins
            if( isset($hook_extra['plugin']) && $hook_extra['plugin'] ) {
                $correct_source = trailingslashit( $remote_source ) . dirname( $hook_extra['plugin'] );
            } 

        }
        
        // We have an adjusted source
        if( isset($correct_source) ) {   
            if( rename($source, $correct_source) ) {
                return $correct_source;
            } else {
                $upgrader->skin->feedback( __("Unable to rename downloaded theme or plugin.", "wp-updater") );
                return new WP_Error();
            }
        }         
        
        return $source;

    }
    
    
    /**
     * Checks our configurations and see if we have everything
     *
     * @return bool|WP_Error true upon success, object WP_Error upon failure
     */
    private function check_config($config) {
        
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