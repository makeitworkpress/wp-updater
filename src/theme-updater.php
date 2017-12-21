<?php
/**
 * This class is responsible for updating themes
 */
namespace MakeitWorkPress\WP_Updater;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class Theme_Updater extends Updater {
    
    /**
     * Contains the information regarding the theme
     *
     * @access protected
     */
    protected $theme;
    
    /**
     * Initializes the theme updater
     *
     * @param array $params The configuration parameters.
     */
    protected function initialize() {
        
        $this->theme    = wp_get_theme( basename(get_template_directory()) );
        $this->slug     = $this->theme->stylesheet;
        $this->version  = $this->theme->version;
        
        add_filter( 'pre_set_site_transient_update_themes', array($this, 'checkUpdate') );
        
    }
    
}