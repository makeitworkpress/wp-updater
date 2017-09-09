<?php
/**
 * This class defines how the Themes and Plugin updater should construct their class
 */
namespace WP_Updater;

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
    protected $request;
    
    /**
     * Contains the slug for the theme or plugin
     *
     * @access protected
     */
    protected $slug;     
    
    /**
     * Contains the url where to update from
     *
     * @access protected
     */
    protected $source;
    
    /**
     * Contains an optional token used for licensed updating
     *
     * @access protected
     */
    protected $token;      
    
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
        $this->source   = $params['source'];   
        $this->token    = $params['token'];
        
        // Determines which platform we are on. Sets $this->platform to the given platform
        $this->platform();
        
        // Initializes the updater
        $this->initialize();
        
    }
    
    /**
     * Sets our platform based on a source url
     */
    private function platform() {
        
        if( strpos( $this->source, 'github.com') !== false ) {
            $this->platform = 'github';
        } elseif( strpos( $this->source, 'gitlab.com') !== false ) {
            $this->platform = 'gitlab';
        } else {
            $this->platform = 'custom';
        }  
        
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
        
        $version = $transient->checked[$this->params['slug']];
        
    }
    
    /**
     * Retrieves theme or plugin info from the server
     *
	 * @param  bool    $def    
	 * @param  string  $action     the API function being performed
	 * @param  object  $args       The arguments with suppied information
	 * @return object  $response   Response with the information
     */
    public final function info( $def, $action, $arg ) {
        
    }
    
    /**
     * Formats the received file into a usuable source for the WordPress upgrader
     */
    public final  function format( $source, $remote_source = NULL, $upgrader = NULL ) {
        // add_filter('upgrader_source_selection', 'format', 10, 3); // Apply like this     
    }    
    
    /**
     * Checks the source, retrieves information and formats the data retrieved to be used by the WordPress Updater.
     */
    protected function source() {
        
        if( ! $this->source )
            return new WP_Error( 'missing', __('Your source to update from is missing.', 'wp-updater') );        
        
        $request = wp_remote_request( $this->source, $this->request );
        
    }   

}