# HereApiConnector
A php library to connect to the here api server side

## Installation

This is the vanilla php integration, there exists also a [dedicated bundle for Symfony](https://github.com/Andreas-Schoenefeldt/HereApiConnectorBundle).

1. You will need a [here api developer account](https://developer.here.com/). 
1. Install the library via [composer](https://getcomposer.org):`$ composer require schoenef/here-api-connector`
1. Set it up:
```php
require_once __DIR__ . '/vendor/autoload.php';

$connector = new HereApiConnector([
    'apiKey' => 'YOUR API KEY',
    'lang' => 'de'
]);

print_r($connector->searchLocation('Schönhauser A', [
    'country' => 'DEU'
]));
```


## Options

This was developed againt version 6.2 of the here api, all options are [documented here](https://developer.here.com/documentation/geocoder-autocomplete/dev_guide/topics/resource-suggest.html)
