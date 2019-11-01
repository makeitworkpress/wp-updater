<?php
/**
 * This class defines how the Themes and Plugin updater should construct their class
 */
namespace MakeitWorkPress\WP_Updater;
use WP_Error as WP_Error;
use stdClass as stdClass;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

abstract class Updater {
    
    /**
     * Contains our updater configurations
     *
     * @access private
     */
    private $config;      
    
    /**
     * Contains optional parameters for the request to the remove source
     *
     * @access private
     */
    private $platform;
    
    /**
     * Contains the slug for the theme or plugin
     *
     * @access protected
     */
    protected $slug;     
    
    /**
     * Contains the source of the theme or plugin and is updated to the url where a request is being made to.
     *
     * @access protected
     */
    private $source;  
    
    /**
     * Contains the current version of the theme or plugin
     *
     * @access protected
     */
    protected $version;
          
    
    /**
     * Constructs the class
     *
     * @param array $params The configuration parameters.
     */
    public function __construct( $params ) {
        
        // Set our defaukt attributes
        $this->config   = $params;
        
        // Determines which platform we are on. Sets $this->platform to the given platform
        $this->platform = $this->getPlatform();
        
        // Initializes the updater from the child class.
        $this->initialize();
        
    }
    
    /**
     * Gets our platform based on a source url and also formats the source for the platform.
     * The source is the url where the request is made to.
     */
    private function getPlatform() {
        
        // Sets our default source, so that source is always set
        $this->source   = $this->config['source'];
        
        // We have github as platform
        if( strpos( $this->config['source'], 'github.com') !== false ) {
            preg_match( '/http(s)?:\/\/github.com\/(?<username>[\w-]+)\/(?<repo>[\w-]+)$/', $this->config['source'], $matches );
            
            if( ! isset($matches['username']) || ! isset($matches['repo']) )
                return new WP_Error( 'wrong', __('Your GitHub Repo is not properly formatted!', 'wp-updater') );
            
            // Reformat source to the API
            $this->source = sprintf( 'https://api.github.com/repos/%s/%s/tags', urlencode($matches['username']), urlencode($matches['repo']) );
            
            return 'github';
            
        } elseif( strpos( $this->config['source'], 'gitlab.com') !== false ) {
            return 'gitlab';
        } else {
            return 'custom';
        } 
        
    }
    
    
    /**
     * Formats the received file into a usuable source for the WordPress upgrader.
     */
    public final  function format( $source, $remote_source = NULL, $upgrader = NULL ) {
        // add_filter('upgrader_source_selection', 'format', 10, 3); // Apply like this     
    }      
    
    /**
     * Determines the use of an initialize function
     *
     * @param array $params Optional parameters whichare passed to the class     
     */
    abstract protected function initialize();    

    /**
     * Checks if we need to update and performs an update when necessary
     *
     * @param   object $transient   The transient stored for update checking
     * @return  object $transient   The transient stored for update checking
     */
    public final function checkUpdate( $transient ) {
        
        if( empty($transient->checked) )
            return $transient;
        
        // Request our source and compare if we have the most recent version
        $data = $this->requestSource();
        
        if( $data && version_compare($this->version, $data->new_version, '<') ) {
            $transient->response[$this->slug] = $this->config['type'] == 'theme' ? (array) $data : $data;
        }
        
        return $transient;
        
    }
    
    /**
     * Checks the source, retrieves information and formats the data retrieved to be used by the WordPress Updater.
     *
     * @return array/boolean/object $data The data with information about the version, package and url 
     */
    protected function requestSource() {
        
        $data = false;   
        
        $request = wp_remote_request( $this->source, $this->config['request'] );
        
        // We have an error
        if( is_wp_error($request) || wp_remote_retrieve_response_code( $request ) !== 200 )
            return $data;
        
        /**
         * Format the data according to our platform
         */
        switch( $this->platform ) {
                
            /**
             * We utilize the github response using the tags api.
             */                
            case 'github':
                $response = json_decode( $request['body'] );
                
                // We don't have any tags
                if( count($response) == 0 )
                    return false;
                
                usort( $response, function($a, $b) {
                    return strcmp( $a->name, $b->name );   
                } );
                                
                $newest             = array_pop( $response );
                $data               = new stdClass();
                $data->new_version  = $newest->name;
                $data->package      = $newest->zipball_url;
                $data->slug         = $this->slug;
                $data->url          = $this->config['source'];
                
            case 'gitlab':
                break;
                
            /**
             * For default urls, we assume the body response is a json response 
             * with new_version, package, slug and url as default properties.
             */
            default:
                $data = json_decode($request['body']);
        }
        
        return $data;
        
    }   

}