# Discovergy-OAuth1

## PHP classes to establish a OAuth1 session and access for [Discovergy](https://discovergy.com/) [API](https://api.discovergy.com/docs/).

### Basic usage

```PHP
use Exception;
use KKo\Discovergy\API1 as DiscovergyAPI;

try {
    $api = new DiscovergyAPI(
        // Required parameters
        $client,                               // Your own application identifier
        $identifier,                           // Login for the Discovergy portal, mostly your email address
        $secret,                               // Password for the Discovergy portal
        // Optional parameters
        sys_get_temp_dir() . '/oauth.json',    // Cache OAuth data
        3600,                                  // TTL for OAuth data in seconds; here 1 hour
        sys_get_temp_dir() . '/meters.json',   // Cache meters data
        3600                                   // TTL for meters data in seconds; here 1 hour
    );
} catch (Exception $e) {
    die($e->getMessage());
}
```

The API class authorizes at the API and tries up to 5 times in case of an error.

With the `$api` instance you can now query the data.

At the moment the `GET` endpoints are implemented (via `__call()`):

#### Metadata

-   `/devices`
-   `/field_names`

#### Measurements

-   `/readings`
-   `/last_reading`
-   `/statistics`
-   `/load_profile`
-   `/raw_load_profile`

#### Disaggregation

-   `/disaggregation`
-   `/activities`

#### Website Access Code

-   `/website_access_code`

#### Virtual meters

-   `/virtual_meters`

Naming convention is: endpoint `/snake_case` must be called as `getCamelCase()`.

`getMeters()` is separate, with lazy load and caching in file.

All methods expect the same required (and optional) paramters as described in the [API docs](https://api.discovergy.com/docs/).

For example the endpoint `/last_reading` expect the required `meterId`.

So the \$api call have to be

```PHP
$last_reading = $api->getLastReading([ 'meterId' => $meterId ]);
```

With optional paramters a call would be

```PHP
$readings = $api->getReadings([ 'meterId' => $meterId, 'resolution' => $resolution, 'from' => $from ]);
```
