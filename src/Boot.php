<?php
/**
 * This class allows you to update themes and plugin through hosted versions on GitHub
 */
namespace MakeitWorkPress\WP_Updater;
use WP_Error as WP_Error;
use WP_Filesystem_Base as WP_Filesystem_Base;
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
         * Remote sources such as the GitHub zipball are named after the owner, repository and commit,
         * so they have to be renamed to the folder in which the theme or plugin is already installed.
         */
        add_filter( 'upgrader_source_selection', [$this, 'source_selection'] , 10, 4 );
        
    }

    /**
     * Adds an updater, either for a theme or plugin
     *
     * @param   array               $config     The configuration parameters to let this updater work.
     * @return  object|boolean      $updater    The registered updater, or false upon failure
     */
    public function add( Array $config = [] ) {
        
        // Default parameters 
        $config = wp_parse_args( $config, [
            'cache'     => 43200,                       // The default cache lifetime for update requests
            'request'   => ['method' => 'GET'],         // The request can be customized with custom parameters, such as a licensing token needed in the request
            'source'    => '',                          // The source, where to retrieve the update from
            'token'     => '',                          // An optional read-only access token, used for private repositories
            'type'      => 'theme'                      // The type to update, either 'theme' or 'plugin'
        ]);

        // Check for errors
        $check = $this->checkConfig($config);
        if( is_wp_error($check) ) {
            echo $check->get_error_message();
            return false;
        }     

        $updater = null;

        // Runs the scripts for updating a theme
        if( $config['type'] == 'theme' ) {
            $updater = new Theme_Updater( $config );
        }
        
        // Runs the scripts for updating a plugin
        if( $config['type'] == 'plugin' ) {
            $updater = new Plugin_Updater( $config );
        }        

        if( $updater ) {
            $this->updaters[] = $updater;
        }

        return $updater;

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
     * Updates our source selection for the upgrader, so the folder of the downloaded package
     * matches the folder of the theme or plugin that is being updated.
     *
     * @param string    $source         The upgrading destination source
     * @param string    $remote_source  The remote source
     * @param object    $upgrader       The upgrader object
     * @param array     $hook_extra     The extra hook
     * @return string   $source         The source
     */
    public function source_selection( $source, $remote_source = NULL, $upgrader = NULL, $hook_extra = NULL ) {

        global $wp_filesystem;

        if( empty($source) || empty($remote_source) || empty($hook_extra) ) {
            return $source;
        }

        $folder = '';

        /**
         * Determines the folder the theme or plugin is currently installed in.
         * WordPress only passes 'plugin' for plugin updates and 'theme' for theme updates,
         * and passes neither for fresh installations.
         */
        if( $upgrader instanceof Plugin_Upgrader && ! empty($hook_extra['plugin']) ) {
            $folder = dirname( $hook_extra['plugin'] );
        } elseif( $upgrader instanceof Theme_Upgrader && ! empty($hook_extra['theme']) ) {
            $folder = $hook_extra['theme'];
        }

        // Single file plugins live in the root of wp-content/plugins and are not supported
        if( ! $folder || $folder === '.' ) {
            return $source;
        }

        // We are not updating a theme or plugin registered by this updater, so leave the source untouched
        if( ! $this->is_registered($folder, $upgrader instanceof Plugin_Upgrader ? 'plugin' : 'theme') ) {
            return $source;
        }

        // The downloaded package is already named correctly
        if( basename( untrailingslashit($source) ) === $folder ) {
            return $source;
        }

        $correct_source = trailingslashit( $remote_source ) . $folder;

        if( $wp_filesystem instanceof WP_Filesystem_Base ) {
            $renamed = $wp_filesystem->move( untrailingslashit($source), untrailingslashit($correct_source) );
        } else {
            $renamed = @rename( untrailingslashit($source), untrailingslashit($correct_source) );
        }

        if( ! $renamed ) {

            if( isset($upgrader->skin) ) {
                $upgrader->skin->feedback( __("Unable to rename downloaded theme or plugin.", "wp-updater") );
            }

            return new WP_Error( 'rename_failed', sprintf( __('Unable to rename the downloaded update to %s.', 'wp-updater'), $folder ) );

        }

        return trailingslashit( $correct_source );

    }

    /**
     * Checks whether a given folder belongs to one of the registered updaters
     *
     * @param   string  $folder The folder within wp-content to check
     * @param   string  $type   The type of the updater, either theme or plugin
     * @return  boolean         True if the folder is registered by this updater, false otherwise
     */
    private function is_registered( string $folder, string $type ): bool {

        foreach( $this->updaters as $updater ) {

            if( $updater->type === $type && $updater->folder === $folder ) {
                return true;
            }

        }

        return false;

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
