<?php
/**
 * This class defines how the Themes and Plugin updater should construct their class
 */
namespace WP_Updater;
use WP_Error as WP_Error;
use stdClass as stdClass;

abstract class Updater {
    
    /**
     * Contains optional parameters for the request to the remove source
     *
     * @access protected
     */
    public $platform;    
    
    /**
     * Contains optional parameters for the request to the remove source
     *
     * @access protected
     */
    private $request;
    
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
     * Contains an optional token used for licensed updating
     *
     * @access protected
     */
    private $token;   
    
    /**
     * Contains the definite url of a theme or plugin
     *
     * @access protected
     */
    private $url;    
    
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
        $this->request  = $params['request'];
        $this->token    = $params['token'];  
        $this->url      = $params['source'];  
        
        // Determines which platform we are on. Sets $this->platform to the given platform
        $this->platform();
        
        // Initializes the updater from the child class.
        $this->initialize();
        
    }
    
    /**
     * Sets our platform based on a source url
     */
    private function platform() {
        
        // We have github as platform
        if( strpos( $this->url, 'github.com') !== false ) {
            $this->platform = 'github';
            
            preg_match( '/http(s)?:\/\/github.com\/(?<username>[\w-]+)\/(?<repo>[\w-]+)$/', $this->url, $matches );
            
            if( !isset($matches['username']) || ! isset($matches['repo']) )
                return new WP_Error( 'wrong', __('Your GitHub Repo is not properly formatted!', 'wp-updater') );
            
            // Reformat source to the API
            $this->source = sprintf( 'https://api.github.com/repos/%s/%s/tags', urlencode($matches['username']), urlencode($matches['repo']) );
            
        } elseif( strpos( $this->url, 'gitlab.com') !== false ) {
            $this->platform = 'gitlab';
            $this->source   = $this->url;
        } else {
            $this->platform = 'custom';
            $this->source   = $this->url;
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
    public final function check( $transient ) {
        
        if( empty($transient->checked) )
            return $transient;
        
        // Our current version
        $version = $transient->checked[$this->slug];
        
    }
    
    /**
     * Checks the source, retrieves information and formats the data retrieved to be used by the WordPress Updater.
     *
     * @return array/boolean/object $data The data with information about the version, package and url 
     */
    protected function source() {
        
        $data = false;   
        
        $request = wp_remote_request( $this->source, $this->request );
        
        // We have an error
        if( is_wp_error($request) || wp_remote_retrieve_response_code( $request ) !== 200 )
            return $data;
        
        /**
         * Format the data according to our platform
         */
        switch( $this->platform ) {
            case 'github':
                $response = json_decode( $request['body'] );
                
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
                $data->url          = $this->url;
                
            case 'gitlab':
                break;
            default:
                $data = $request['body'];
        }
        
        return $data;
        
    }   

}