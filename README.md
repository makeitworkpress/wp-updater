# wp-updater
The WP Updater scripts allows to updates themes or plugins from external sources such as GitHub. Currently, only GitHub is supported. 
WP Updater is maintained by [Make it WorkPress](https://makeitwork.press/scripts/wp-updater/).

## Usage
You can include the WP Updater script in your theme or plugin, and required all the included classes manually or use a PHP autoloader. You can read more about autoloading in [the readme of wp-autoload](https://github.com/makeitworkpress/wp-autoload).


### Installation
After you have correctly included all WP Updater script files or used an autoloader, the updater can be initialized. 

For plugins:
```php
    $updater = MakeitWorkPress\WP_Updater\Boot::instance();
    $updater->add(['type' => 'plugin', 'source' => 'https://github.com/yourname/plugin-on-github']);
```

And for themes:
```php
    $updater = MakeitWorkPress\WP_Updater\Boot::instance();
    $updater->add(['type' => 'theme', 'source' => 'https://github.com/yourname/theme-on-github']);
```

There are few things that should be noted:
*Because the updater is placed within a theme or plugin itself, it only functions when this given theme or plugin is active.
*For plugins, the folder name should be similar as the main file initilizing your plugin. For example, ``plugin-name/plugin-name.php``. 