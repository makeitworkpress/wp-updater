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
     * The default parameters for running the updater
     * @access private
     */
    private $config;
    
    /**
     * Contains the updater class for either a theme or plugin
     * @access private
     */
    private $updater;    
    
    /**
     * Constructor
     * The constructor accepts for now the github url
     *
     * @param array $params The configuration parameters to let this updater work.
     *
     * @return void
     */
    public function __construct( $params ) {
        
        // This script only works in admin context
        if( ! is_admin() ) {
            return;
        }
        
        // Default parameters 
        $defaults = [
            'cache'     => 43200,                       // The default cache lifetime for update requests
            'request'   => ['method' => 'GET'],         // The request can be customized with custom parameters, such as a licensing token needed in the request
            'source'    => '',                          // The source, where to retrieve the update from
            'type'      => 'theme',                     // The type to update, either theme or plugin
            'verifySSL' => true
        ];
        
        $this->config = wp_parse_args( $params, $defaults );
        
        /** 
         * If we are missing correctly formatted parameters, bail out.
         */
        if( is_wp_error( $this->checkParameters() ) ) {
            echo $check->get_error_message();
            return;
        }
        
        
        /**
         * Run our updater scripts
         */

        // Runs the scripts for updating a theme
        if( $this->config['type'] == 'theme' ) {
            $this->updater = new Theme_Updater( $this->config );
        }
        
        // Runs the scripts for updating a plugin
        if( $this->config['type'] == 'plugin' ) {
            $this->updater = new Plugin_Updater( $this->config );
        }
        
        /**
         * Check if we need to verify SSL
         *
         * @param array $args The arguments for the verification
         * @param string $url The url
         *
         * @return array $args
         */
        if( $this->config['verifySSL'] ) {
            add_filter( 'http_request_args', [$this, 'verifySSL'], 10, 2 );
        }
        
                            
        /** 
         * Renames the source during upgrading, so it fits the structure from WordPress
         *
         * @param string    $source         The upgrading destination source
         * @param string    $remote_sourc   The remote source
         * @param object    $upgrader       The upgrader object
         */
        add_filter( 'upgrader_source_selection', [$this, 'sourceSelection'] , 10, 4 );
        
    } 

    /**
     * Filters our SSL verification to true
     * 
     * @param Array $args The arguments for the http request
     * @param String $url  The url for the request
     * @return Array $args The modified arguments
     */
    public function verifySSL( $args, $url ) {
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
     * @return string     $source       The source
     */
    public function sourceSelection( $source, $remote_source = NULL, $upgrader = NULL, $hook_extra = NULL ) {

        if( isset($source, $remote_source) ) {

            // Retrieves the source for themes
            if( isset($upgrader->skin->theme_info->stylesheet) && $upgrader->skin->theme_info->stylesheet ) {
                $correctSource = trailingslashit( $remote_source . '/' . $upgrader->skin->theme_info->stylesheet );
            }

            // Retrieves for plugins
            if( isset($hook_extra['plugin']) && $hook_extra['plugin'] ) {
                $correctSource = trailingslashit( $remote_source ) . dirname( $hook_extra['plugin'] );
            } 

        }
        
        // We have an adjusted source
        if( isset($correctSource) ) {
                
            if( rename($source, $correctSource) ) {
                return $correctSource;
            } else {
                $upgrader->skin->feedback( __("Unable to rename downloaded theme or plugin.", "wp-updater") );
                return new WP_Error();
            }

        }         
        
        return $source;

    }
    
    
    /**
     * Checks our parameters and see if we have everything
     * @todo Adds a sanitizer which checks urls, so that they are correct.
     *
     * @return boolean true upon success, object WP_Error upon failure
     */
    private function checkParameters() {
        
        if( $this->config['type'] !== 'theme' && $this->config['type'] !== 'plugin' ) {
            return new WP_Error( 'wrong', __( "Your updater type is not theme or plugin!", "wp-updater" ) );  
        }       
        
        if( empty($this->config['type']) ) {
            return new WP_Error( 'missing', __( "You are missing what to update, either theme or plugin.", "wp-updater" ) );  
        }      
        
        if( empty($this->config['source']) ) {
            return new WP_Error( 'missing', __( "You are missing the url where to update from.", "wp-updater" ) );
        }
        
        return true;
        
    }
    
}