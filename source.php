<?php
/**
 * This class determines the design of sourcing extensions
 */
namespace WP_Updater;

abstract class Source {
    
    // Returns the version information and optional file urls.
    abstract protected function version();    
    
    // Returns basic information concerning the plugin or theme
    abstract protected function info();
    
    // Retrieves the remote information
    public function remote() {
        
    }
    
}