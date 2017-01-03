# Upstream Imbo plugin

Configure a upstream Imbo server where unknown image-identifiers will be replicated from.

## Installation

### Setting up the dependencies

If you've installed Imbo through composer, getting the upstream plugin up and running is easy. Just add `tv2/imbo-plugin-upstream` as a dependency and run `composer update`.

```json
{
    "require": {
        "tv2/imbo-plugin-upstream": "dev-master",
    }
}
```

### Configuring imbo

Once you've got the plugin installed, you need to configure your upstream. An example configuration can be found in `config/config.php.dist`. If you copy the file to your configuration-directory, rename it to something like `upstream.php` and adjust the parameters, you should be good to go.

## License

Copyright (c) 2016, TV 2 Danmark A/S

Licensed under the MIT license.
