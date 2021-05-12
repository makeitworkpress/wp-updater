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
     * Contains our updater configurations, as inherited from the Boot::add
     * @access private
     */
    private $config;      
    
    /**
     * Contains optional parameters for the request to the remove source
     * @access private
     */
    private $platform;

    /**
     * Contains the transient name for the item to check
     * @access private
     */
    private $transient;

    /**
     * Contains the source of the theme or plugin where the api request is made to.
     * @access protected
     */
    private $source; 
    
    /**
     * Contains the current version of the theme or plugin, which is set by the Plugin_Updater or Theme_Updater child class.
     * @access protected
     */
    protected $version;    
    
    /**
     * Contains the slug for the theme or plugin, which is set by the Plugin_Updater or Theme_Updater child class.
     * @access public
     */
    public $slug; 

    /**
     * Contains the folder for a given plugin, which is set by the Plugin_Updater child class
     * @access public
     */
    public $folder;      
    
    /**
     * Constructs the class
     *
     * @param array $params The configuration parameters.
     */
    public function __construct( Array $config = [] ) {
        
        // Set our attributes
        $this->config   = $config;

        // Determines which platform we are on. Returns the given platform and also sets $this->source to the source of the download.
        $this->platform = $this->getPlatform();        
        
        // Initializes the updater from the child class, and defines the slug for the theme or plugin.
        $this->initialize();

        // If we don't have a slug, bail out
        if( ! $this->slug ) {
            return;
        }

        $this->transient = 'wp_updater_' . md5(sanitize_key($this->slug));

        // Removes the transient or cache after ann update has executed
        if( $this->config['type'] == 'theme' ) {
            add_filter( 'pre_set_site_transient_update_themes', [$this, 'checkUpdate'] );
            add_action( 'delete_site_transient_update_themes', [$this, 'clearTransient'], 10, 2 );
        }

        if( $this->config['type'] == 'plugin' ) {
            add_filter( 'pre_set_site_transient_update_plugins', [$this, 'checkUpdate'] );
            add_action( 'delete_site_transient_update_plugins', [$this, 'clearTransient'], 10, 2 );
        }        

        // Deletes our transients if we're force-checking the updater
        $this->clearTransientForced();
        
    }

    /**
     * The initialize function is used by the plugin and theme updater class to define settings respectively
     */
    abstract protected function initialize();      
    
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
            
            if( ! isset($matches['username']) || ! isset($matches['repo']) ) {
                return new WP_Error( 'wrong', __('Your GitHub Repo is not properly formatted!', 'wp-updater') );
            }
            
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
     * Checks if we need to update and performs an update when necessary
     *
     * @param   object $transient   The transient stored for update checking
     * @return  object $transient   The transient stored for update checking
     */
    public final function checkUpdate( $transient ) {
        
        if( empty($transient->checked) ) {
            return $transient;
        }
        
        // Request our source and compare if we have the most recent version
        $data = $this->requestSource();
        
        // If we are updating a theme, the slug for the theme will be used. Otherwise, the folder + plugin file is used.
        if( $data && version_compare($this->version, $data->new_version, '<') ) {
            $transient->response[$data->plugin ? $data->plugin : $data->slug] = $this->config['type'] == 'theme' ? (array) $data : $data;
        }
        
        return $transient;
        
    }
    
    /**
     * Checks the source, retrieves information and formats the data retrieved to be used by the WordPress Updater.
     *
     * @return array/boolean/object $data The data with information about the version, package and url 
     */
    protected function requestSource() {
        
        // Check our transient before retrieving remote updates
        $data       = get_transient( $this->transient );

        // Return early with data from our transient
        if( $data ) { 
            return $data;
        }       
        
        // Otherwise, do the remote request
        $request    = wp_remote_request( $this->source, $this->config['request'] );

        // We have no error, continue
        if( ! is_wp_error($request) && ! wp_remote_retrieve_response_code( $request ) !== 200 && ! empty($request['body']) ) {

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
                    if( ! is_array($response) || count($response) == 0 ) {
                        $data               = new stdClass();
                        $data->new_version  = 0;
                        return $data;
                    }
                    
                    usort( $response, function($a, $b) {
                        return strcmp( $a->name, $b->name );   
                    } );
                                    
                    $newest             = array_pop( $response ); // Retrieves the latest release from Github
                    $data               = new stdClass();
                    $data->new_version  = $newest->name;
                    $data->package      = $newest->zipball_url;
                    $data->plugin       = $this->config['type'] == 'plugin'  ? $this->folder . DIRECTORY_SEPERATOR . $this->slug . '.php' : '';
                    $data->slug         = $this->slug;
                    $data->url          = $this->config['source'];
                    
                    break;
                    
                /**
                 * For default urls, we assume the body response is a json response 
                 * with new_version, package, slug and url as default properties.
                 */
                default:
                    $data = json_decode($request['body']);
                    
            }            
            
            set_transient( $this->transient, $data, $this->config['cache'] );

        }
        
        return $data;
        
    } 
    
    /**
     * Clears our transient cache after updating
     */
    public function clearTransient() {
        delete_transient( $this->transient );
    } 

    /**
     * Clears the transient when forced from the upgrader
     */
    private function clearTransientForced() {
		global $pagenow;

		if ( 'update-core.php' === $pagenow && isset($_GET['force-check']) ) {
			$this->clearTransient();
		}        
    }

}