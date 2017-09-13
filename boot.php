<?php
/**
 * This class allows you to update themes and plugin through hosted versions on GitHub
 */
namespace WP_Updater;
use WP_Error as WP_Error;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Boot {
    
    /**
     * The default parameters for running the updater
     * @access private
     */
    private $config;
    
	/**
	 * Stores data retrieved from GitHub
	 * @access private
	 */
	private $github;    
    
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
        if( ! is_admin() )
            return;
        
        // Default parameters 
        $defaults = array(
            'request'   => array( 'method' => 'GET' ),  // The request can be customized with custom parameters, such as a licensing token needed in the request
            'source'    => '',                          // The source, where to retrieve the update from
            'type'      => 'theme',                     // The type to update, either theme or plugin
            'verifySSL' => true
        );
        
        $this->config = wp_parse_args( $params, $defaults );
        
        /** 
         * If we are missing correctly formatted parameters, bail out.
         */
        $check = $this->checkParameters();
        
        if( is_wp_error( $check ) ) {
            echo $check->get_error_message();
            return;
        }
        
        
        /**
         * Run our updater scripts
         */
        
        // Runs the scripts for updating a theme
        if( $this->config['type'] == 'theme' )
            new Theme_Updater( $this->config );
        
        // Runs the scripts for updating a plugin
        if( $this->config['type'] == 'plugin' )
            new Plugin_Updater( $this->config );
        
        /**
         * Check if we need to verify SSL
         *
         * @param array $args The arguments for the verification
         * @param string $url The url
         *
         * @return array $args
         */
        if( $this->config['verifySSL'] ) {
            add_filter( 'http_request_args', function( $args, $url ) {
                $args[ 'sslverify' ] = true;
                return $args;            
            }, 10, 2 );
        }
        
                
        /** 
         * Renames the source during upgrading, so it fits the structure from WordPress
         *
         * @param string    $source         The upgrading destination source
         * @param string    $remote_sourc   The remote source
         * @param object    $upgrader       The upgrader object
         */
        add_filter( 'upgrader_source_selection', function( $source, $remote_source = NULL, $upgrader = NULL ) {
            
            if( isset($source, $remote_source, $upgrader->skin->theme_info->stylesheet) ) {
                $correctSource = $remote_source . '/' . $upgrader->skin->theme_info->stylesheet . '/';
                
                if( rename($source, $correctSource) ) {
                    return $correctSource;
                } else {
                    $upgrader->skin->feedback( "Unable to rename downloaded theme." );
                    return new WP_Error();
                }
                
            }

            return $source; 
            
        }, 10, 3 );
        
    }   
    
    /**
     * Checks our parameters and see if we have everything
     * @todo Adds a sanitizer which checks urls, so that they are correct.
     *
     * @return boolean true upon success, object WP_Error upon failure
     */
    private function checkParameters() {
        
        if( $this->config['type'] !== 'theme' && $this->config['type'] !== 'plugin' )
            return new WP_Error( 'wrong', __( "Your updater type is not theme or plugin!", "wp-updater" ) );         
        
        if( empty($this->config['type']) )
            return new WP_Error( 'missing', __( "You are missing what to update, either theme or plugin.", "wp-updater" ) );        
        
        if( empty($this->config['source']) )
            return new WP_Error( 'missing', __( "You are missing the url where to update from.", "wp-updater" ) );
        
        return true;
        
    }
    
}