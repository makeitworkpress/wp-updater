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
     * @access protected
     */
    protected $config;

    /**
     * Contains an error for a malformed source, if any
     * @access private
     */
    private $error = null;

    /**
     * Contains the folder for a given plugin or theme, which is set by the child class.
     * This is the folder as it exists within wp-content and to which an update is restored.
     * @access public
     */
    public $folder;

    /**
     * Contains the public (human readable) url to the repository, without any credentials
     * @access private
     */
    private $homepage = '';

    /**
     * Contains optional parameters for the request to the remove source
     * @access private
     */
    private $platform;

    /**
     * Contains the owner and name of the remote repository, if we are on a known platform
     * @access private
     */
    private $repository = [];

    /**
     * Contains the transient name for the item to check
     * @access private
     */
    private $transient;

    /**
     * Contains the slug for the theme or plugin, which is set by the Plugin_Updater or Theme_Updater child class.
     * @access public
     */
    public $slug;

    /**
     * Contains the source of the theme or plugin where the api request is made to.
     * @access private
     */
    private $source;

    /**
     * Contains the access token for private repositories, if any
     * @access private
     */
    private $token = '';

    /**
     * Contains the type of the updater, either theme or plugin
     * @access public
     */
    public $type = 'theme';

    /**
     * Contains the current version of the theme or plugin, which is set by the Plugin_Updater or Theme_Updater child class.
     * @access protected
     */
    protected $version;

    /**
     * Constructs the class
     *
     * @param array $config The configuration parameters.
     */
    public function __construct( array $config = [] ) {

        // Set our attributes
        $this->config   = $config;
        $this->type     = isset($config['type']) ? $config['type'] : 'theme';

        // Determines which platform we are on. Returns the given platform and also sets $this->source to the source of the download.
        $this->platform = $this->get_platform();

        // The source could not be understood, so display the error and bail out.
        if( is_wp_error($this->error) ) {
            $this->show_error();
            return;
        }

        // Initializes the updater from the child class, and defines the slug and folder for the theme or plugin.
        $this->initialize();

        // If we don't have a slug, bail out
        if( ! $this->slug ) {
            return;
        }

        $this->transient = 'wp_updater_' . md5( $this->type . '_' . sanitize_key($this->slug) );

        // Removes the transient or cache after an update has executed
        if( $this->type == 'theme' ) {
            add_filter( 'pre_set_site_transient_update_themes', [$this, 'check_update'] );
            add_action( 'delete_site_transient_update_themes', [$this, 'clear_transient'] );
        }

        if( $this->type == 'plugin' ) {
            add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check_update'] );
            add_action( 'delete_site_transient_update_plugins', [$this, 'clear_transient'] );
        }

        /**
         * Authorizes requests to private repositories. This is needed for the api request performed by this class,
         * but also for the download of the package itself, which is performed by download_url() within WordPress core.
         */
        if( $this->token ) {
            add_filter( 'http_request_args', [$this, 'authorize_request'], 15, 2 );
        }

        // Deletes our transients if we're force-checking the updater
        $this->clear_transient_forced();

    }

    /**
     * The initialize function is used by the plugin and theme updater class to define settings respectively
     */
    abstract protected function initialize();

    /**
     * Gets our platform based on a source url and also formats the source for the platform.
     * The source is the url where the request is made to.
     *
     * @return string The platform that is used
     */
    private function get_platform(): string {

        $source         = isset($this->config['source']) ? trim($this->config['source']) : '';

        // Sets our defaults, so both source and homepage are always set
        $this->source   = $source;
        $this->homepage = $source;

        $url            = wp_parse_url( $source );
        $host           = isset($url['host']) ? strtolower($url['host']) : '';

        // Determine the access token used for private repositories
        $this->token    = $this->get_token( $url );

        // We have github as platform
        if( $host === 'github.com' || $host === 'www.github.com' ) {

            $repository = $this->get_repository( $url );

            if( ! $repository ) {
                $this->error = new WP_Error( 'wrong', sprintf( __('WP Updater: your GitHub repository (%s) is not properly formatted. Please use https://github.com/owner/repository.', 'wp-updater'), $source ) );
                return 'github';
            }

            $this->repository   = $repository;
            $this->homepage     = sprintf( 'https://github.com/%s/%s', $repository['owner'], $repository['name'] );

            // Reformat source to the API
            $this->source       = sprintf( 'https://api.github.com/repos/%s/%s/tags?per_page=100', $repository['owner'], $repository['name'] );

            return 'github';

        }

        if( $host === 'gitlab.com' || $host === 'www.gitlab.com' ) {
            return 'gitlab';
        }

        return 'custom';

    }

    /**
     * Retrieves the owner and name of the repository from a parsed source url
     *
     * @param   array           $url    The parsed source url
     * @return  array|boolean           An array with the owner and name of the repository, or false if it could not be determined
     */
    private function get_repository( $url ) {

        $path       = isset($url['path']) ? trim($url['path'], '/') : '';
        $path       = preg_replace( '/\.git$/i', '', $path );
        $segments   = $path ? explode( '/', $path ) : [];

        if( count($segments) < 2 || empty($segments[0]) || empty($segments[1]) ) {
            return false;
        }

        return ['owner' => $segments[0], 'name' => $segments[1]];

    }

    /**
     * Retrieves the access token for a private repository.
     * The token may be passed explicitly through the configurations, as credentials within
     * the source url (such as https://token@github.com/owner/repository) or as a query argument.
     *
     * @param   array   $url    The parsed source url
     * @return  string          The access token, or an empty string if there is none
     */
    private function get_token( $url ): string {

        if( ! empty($this->config['token']) ) {
            return trim( (string) $this->config['token'] );
        }

        // Tokens passed as a query argument, for example https://github.com/owner/repository?access_token=token
        if( ! empty($url['query']) ) {
            $query = [];
            parse_str( $url['query'], $query );

            foreach( ['access_token', 'token', 'private_token'] as $key ) {
                if( ! empty($query[$key]) ) {
                    return trim( (string) $query[$key] );
                }
            }
        }

        /**
         * Tokens passed as credentials, either as https://token@github.com/owner/repository
         * or as https://username:token@github.com/owner/repository
         */
        if( ! empty($url['pass']) ) {
            return rawurldecode( $url['pass'] );
        }

        if( ! empty($url['user']) ) {
            return rawurldecode( $url['user'] );
        }

        return '';

    }

    /**
     * Adds the authorization header to requests made to the repository of this updater,
     * so that private repositories may be read and downloaded.
     *
     * @param   array   $args   The arguments for the http request
     * @param   string  $url    The url for the request
     * @return  array   $args   The modified arguments
     */
    public function authorize_request( $args, $url ) {

        if( ! $this->token || ! $this->repository || $this->platform !== 'github' ) {
            return $args;
        }

        $parsed = wp_parse_url( $url );
        $host   = isset($parsed['host']) ? strtolower($parsed['host']) : '';

        // Only ever send our token to GitHub itself
        if( ! in_array( $host, ['api.github.com', 'github.com', 'codeload.github.com'], true ) ) {
            return $args;
        }

        // And only for the repository belonging to this updater
        $path       = isset($parsed['path']) ? $parsed['path'] : '';
        $repository = $this->repository['owner'] . '/' . $this->repository['name'];

        if( stripos( $path, $repository ) === false ) {
            return $args;
        }

        if( ! isset($args['headers']) || ! is_array($args['headers']) ) {
            $args['headers'] = [];
        }

        // Bearer is supported by both classic and fine-grained personal access tokens
        $args['headers']['Authorization'] = 'Bearer ' . $this->token;

        return $args;

    }

    /**
     * Checks if we need to update and performs an update when necessary
     *
     * @param   object $transient   The transient stored for update checking
     * @return  object $transient   The transient stored for update checking
     */
    public final function check_update( $transient ) {

        if( empty($transient->checked) ) {
            return $transient;
        }

        // Request our source and compare if we have the most recent version
        $data = $this->request_source();

        if( is_array($data) ) {
            $data = (object) $data;
        }

        if( ! is_object($data) || empty($data->new_version) ) {
            return $transient;
        }

        // If we are updating a theme, the slug for the theme will be used. Otherwise, the folder + plugin file is used.
        if( version_compare($this->version, $data->new_version, '<') ) {
            $transient->response[ ! empty($data->plugin) ? $data->plugin : $data->slug] = $this->type == 'theme' ? (array) $data : $data;
        }

        return $transient;

    }

    /**
     * Checks the source, retrieves information and formats the data retrieved to be used by the WordPress Updater.
     *
     * @return array|bool|object $data The data with information about the version, package and url
     */
    protected function request_source() {

        // Check our transient before retrieving remote updates
        $data       = get_transient( $this->transient );

        // Return early with data from our transient
        if( $data ) {
            return $data;
        }

        // Otherwise, do the remote request
        $request    = wp_remote_request( $this->source, $this->get_request_args() );

        if( is_wp_error($request) ) {
            return false;
        }

        $body       = wp_remote_retrieve_body( $request );

        if( wp_remote_retrieve_response_code( $request ) != 200 || empty($body) ) {
            return false;
        }

        /**
         * Format the data according to our platform
         */
        switch( $this->platform ) {

            /**
             * We utilize the github response using the tags api.
             */
            case 'github':
                $data = $this->format_github_response( $body );
                break;

            /**
             * For default urls, we assume the body response is a json response
             * with new_version, package, slug and url as default properties.
             */
            default:
                $data = json_decode( $body );

        }

        if( $data ) {
            set_transient( $this->transient, $data, isset($this->config['cache']) ? $this->config['cache'] : 43200 );
        }

        return $data;

    }

    /**
     * Returns the arguments used for the request to our source
     *
     * @return array The request arguments
     */
    private function get_request_args(): array {

        $args = isset($this->config['request']) && is_array($this->config['request']) ? $this->config['request'] : [];

        if( $this->platform === 'github' ) {

            if( ! isset($args['headers']) || ! is_array($args['headers']) ) {
                $args['headers'] = [];
            }

            $args['headers'] = wp_parse_args( $args['headers'], [
                'Accept'                => 'application/vnd.github+json',
                'X-GitHub-Api-Version'  => '2022-11-28'
            ] );

        }

        return $args;

    }

    /**
     * Formats the response from the GitHub tags api to the data used by the WordPress updater
     *
     * @param   string          $body   The body of the response
     * @return  object|boolean  $data   The formatted data, or false if the response could not be read
     */
    private function format_github_response( string $body ) {

        $response   = json_decode( $body );
        $data       = new stdClass();

        // We don't have any tags
        if( ! is_array($response) || count($response) == 0 ) {
            $data->new_version = 0;
            return $data;
        }

        $tags       = [];
        $fallback   = [];

        foreach( $response as $tag ) {

            if( empty($tag->name) ) {
                continue;
            }

            // Tags are commonly prefixed with a v, such as v1.0.2
            $version            = ltrim( $tag->name, 'vV' );
            $fallback[$version] = $tag;

            // Only consider tags which actually look like a version number
            if( preg_match('/^\d+(\.\d+)*/', $version) ) {
                $tags[$version] = $tag;
            }

        }

        $tags = $tags ? $tags : $fallback;

        if( ! $tags ) {
            $data->new_version = 0;
            return $data;
        }

        // Sorts our versions from low to high, so the latest release is last
        uksort( $tags, 'version_compare' );

        $versions           = array_keys( $tags );
        $version            = end( $versions );
        $newest             = $tags[$version];

        $data->new_version  = $version;
        $data->package      = ! empty($newest->zipball_url) ? $newest->zipball_url : sprintf( 'https://api.github.com/repos/%s/%s/zipball/refs/tags/%s', $this->repository['owner'], $this->repository['name'], $newest->name );
        $data->plugin       = $this->type == 'plugin' ? $this->folder . '/' . $this->slug . '.php' : '';
        $data->slug         = $this->slug;
        $data->theme        = $this->type == 'theme' ? $this->folder : '';
        $data->url          = $this->homepage;

        return $data;

    }

    /**
     * Clears our transient cache after updating
     */
    public function clear_transient(): void {
        delete_transient( $this->transient );
    }

    /**
     * Displays the error for a malformed source in the admin
     */
    private function show_error(): void {

        $message = $this->error->get_error_message();

        add_action( 'admin_notices', function() use ( $message ) {
            printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html($message) );
        } );

    }

    /**
     * Clears the transient when forced from the upgrader
     */
    private function clear_transient_forced(): void {
		global $pagenow;

		if ( 'update-core.php' === $pagenow && isset($_GET['force-check']) ) {
			$this->clear_transient();
		}
    }

}
