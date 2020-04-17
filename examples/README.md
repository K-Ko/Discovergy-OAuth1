# Examples

## `api-get.php`

Refer to [Discovergy API](https://api.discovergy.com/docs/) for valid GET endpoints and their parameters.

The general call syntax ist the following:

    php -f api-get.php <identifier> <secret> <endpoint> [<param1=value1> [<param2=value2> ...]]

-   identifier - Login for the Discovergy portal, mostly your email address
-   secret - Password for the Discovergy portal
-   endpoint - see [docs](https://api.discovergy.com/docs/), **without slash**!
-   params - different for each endpoint

The script return the API result as JSON string or the error trace on STDERR.

To fetch the meters endpoint

    php -f api-get.php <identifier> <secret> meters

All other endpoints need mostly the `meterId` parameter.

To fetch the last reading of a meter, use the `fullSerialNumber` provided by the meters call.

    php -f api-get.php <identifier> <secret> last_reading meterId=1234567890

To get the raw data for further processing redirect the STDERR

    php -f api-get.php <identifier> <secret> last_reading meterId=1234567890 2>/dev/null
