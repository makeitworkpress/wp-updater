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

### Private repositories
Private GitHub repositories are supported by supplying a read-only access token, such as a fine-grained personal access
token with read access to the repository contents. The token may be passed in the source url itself:

```php
    $updater = MakeitWorkPress\WP_Updater\Boot::instance();
    $updater->add(['type' => 'plugin', 'source' => 'https://your-token@github.com/yourname/private-plugin']);
```

Both ``https://your-token@github.com/yourname/private-plugin``, ``https://yourname:your-token@github.com/yourname/private-plugin``
and ``https://github.com/yourname/private-plugin?access_token=your-token`` are recognized.

Alternatively, and preferably, the token can be passed separately so it is kept out of the url that is displayed within the admin:

```php
    $updater = MakeitWorkPress\WP_Updater\Boot::instance();
    $updater->add([
        'type'      => 'plugin', 
        'source'    => 'https://github.com/yourname/private-plugin',
        'token'     => 'your-token'
    ]);
```

The token is added as an ``Authorization: Bearer`` header to the request that checks for new versions, as well as to the
download of the package itself. It is only ever sent to ``api.github.com``, ``github.com`` and ``codeload.github.com``,
and only for requests concerning the configured repository. Do not commit tokens to a public repository; read them from
``wp-config.php`` or an environment variable instead.

### Configurations
| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| ``cache`` | int | ``43200`` | The lifetime in seconds of the transient in which the update check is cached. |
| ``request`` | array | ``['method' => 'GET']`` | Custom arguments for the remote request, as accepted by ``wp_remote_request``. |
| ``source`` | string | ``''`` | The url of the repository to update from. |
| ``token`` | string | ``''`` | An optional read-only access token, used for private repositories. |
| ``type`` | string | ``'theme'`` | The type to update, either ``theme`` or ``plugin``. |

### Versions
Updates are resolved from the tags of the repository, so publishing a release (which creates a tag) is enough. Tags are
compared with ``version_compare`` and an optional ``v`` prefix is ignored, meaning that both ``1.10.2`` and ``v1.10.2``
are read as version ``1.10.2``.
