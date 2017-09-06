<?php
/**
 * This class defines how the Themes and Plugin updater should construct their class
 */
namespace WP_Updater;

abstract class Updater {
    
    /**
     * The updater parameters
     *
     * @access Private
     */
    private $params;
    
    /**
     * Containts the current version of the theme or plugin
     *
     * @access Private
     */
    protected $version;    
    
    /**
     * Constructs the class
     *
     * @param array $params The configuration parameters.
     */
    public function __construct( $params ) {
        $this->params = params;   
        
        // Initializes the updater
        $this->initialize( $params );
        
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
    public final function call( $def, $action, $arg ) {
        
    }
    
    /**
     * Checks the source and set-ups which API to use, and subsequently retrieves data.
     *
     * @param string    $source The url where to update from
     * @param array     $tokens Optional tokens to verify a request from the given API
     */
    protected function source( $source, $tokens = array() ) {
        return new GitHub($source);
    }

}