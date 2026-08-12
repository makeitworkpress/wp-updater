<?php
/**
 * This class is responsible for updating themes
 */
namespace MakeitWorkPress\WP_Updater;
use MakeitWorkPress\WP_Updater\Updater as Updater;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Theme_Updater extends Updater {
    
    /**
     * Contains the information regarding the theme
     * 
     * @var object
     * @access protected
     */
    protected $theme;
    
    /**
     * Initializes the theme updater
     *
     * @param array $params The configuration parameters.
     */
    protected function initialize(): void {
        
        $this->theme    = wp_get_theme( basename(get_template_directory()) );

        /**
         * The stylesheet is the directory name of the theme within wp-content/themes and is also
         * the key WordPress uses within the update transient, so it is used as the slug as well.
         */
        $this->folder   = $this->theme->get_stylesheet();
        $this->slug     = $this->folder;
        $this->version  = $this->theme->version;  // Current version of the theme
        
    }
    
}