<?php
/**
 *
 */
namespace KKo\OAuth1;

/**
 *
 */
use Exception;

/**
 * OAuth 1.0 session
 */
final class Session
{
    /**
     * Discovergy API base URL
     *
     * @var string
     */
    public static $baseUrl = 'https://api.discovergy.com/public/v1';

    /**
     * @var array Debug messages
     */
    public static $debug = [];

    /**
     * @var array
     */
    public static $curlInfo = [];

    /**
     * Class constructor
     *
     * @param  string  $consumerKey
     * @param  string  $consumerSecret
     * @param  string  $token
     * @param  string  $tokenSecret
     */
    public function __construct($consumerKey, $consumerSecret, $token, $tokenSecret)
    {
        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->token          = $token;
        $this->tokenSecret    = $tokenSecret;
    }

    /**
     * Authorize client and return valid session
     *
     * @throws Exception
     * @param  string $client
     * @param  string $identifier
     * @param  string $secret
     * @return \KKo\OAuth1\Session
     */
    public static function authorize($client, $identifier, $secret)
    {
        // ------------------------------------------------------------------
        // 1. Get consumer token
        // ------------------------------------------------------------------

        $url    = self::$baseUrl . '/oauth1/consumer_token';
        $fields = [ 'client' => $client ];
        $res    = json_decode(self::curlPost($url, $fields), true);

        if (!isset($res['key'], $res['secret'])) {
            throw new Exception('Get customer token failed (1) ' . json_encode(self::getLastCurlInfo()));
        }

        $consumerKey    = $res['key'];
        $consumerSecret = $res['secret'];

        // ------------------------------------------------------------------
        // 2. Get request token
        // ------------------------------------------------------------------

        $url    = self::$baseUrl . '/oauth1/request_token';
        $fields = self::getBaseOauthFields($consumerKey);

        $fields['oauth_signature'] = self::sign('POST', $url, $fields, $consumerSecret . '&');

        $res = self::curlPost($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_token'], $res['oauth_token_secret'])) {
            throw new Exception('Get request token failed (2) ' . json_encode(self::getLastCurlInfo()));
        }

        // Internal used for steps 3 & 4
        $token       = $res['oauth_token'];
        $tokenSecret = $res['oauth_token_secret'];

        // ------------------------------------------------------------------
        // 3. Authorize user
        // ------------------------------------------------------------------

        $url    = self::$baseUrl . '/oauth1/authorize';
        $fields = [
            'oauth_token' => $token,
            'email'       => $identifier,
            'password'    => $secret,
        ];

        $res = self::curlGet($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_verifier'])) {
            throw new Exception('Authorize user failed (3) ' . json_encode(self::getLastCurlInfo()));
        }

        $oauthVerifier = $res['oauth_verifier'];

        // ------------------------------------------------------------------
        // 4. Get access token
        // ------------------------------------------------------------------

        $url = self::$baseUrl . '/oauth1/access_token';

        $fields = self::getBaseOauthFields($consumerKey);
        $fields['oauth_token']     = $token;
        $fields['oauth_verifier']  = $oauthVerifier;

        $fields['oauth_signature'] = self::sign('POST', $url, $fields, $consumerSecret . '&' . $tokenSecret);

        $res = self::curlPost($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_token'], $res['oauth_token_secret'])) {
            throw new Exception('Get access token failed (4) ' . json_encode(self::getLastCurlInfo()));
        }

        return new self($consumerKey, $consumerSecret, $res['oauth_token'], $res['oauth_token_secret']);
    }

    /**
     * Get last cUrl info data
     */
    public static function getLastCurlInfo()
    {
        return count(self::$curlInfo) ? self::$curlInfo[count(self::$curlInfo) - 1] : null;
    }

    /**
     * Needed for cache credentials extern for reuse
     */
    public function getSecrets()
    {
        return [ $this->consumerKey, $this->consumerSecret, $this->token, $this->tokenSecret ];
    }

    /**
     * Get data from an URL using signed request
     *
     * @param  string  $url
     * @param  array   $params
     * @return string
     */
    public function get($url, $params = [])
    {
        $fields = self::getBaseOauthFields($this->consumerKey);
        $fields['oauth_token'] = $this->token;
        $fields = array_replace($fields, $params);

        $fields['oauth_signature'] = self::sign(
            'GET',
            $url,
            $fields,
            $this->consumerSecret . '&' . $this->tokenSecret
        );

        return self::curlGet(
            $url,
            $params,
            ['Content-Type: application/json', self::OAuthHeader($fields)]
        );
    }

    /**
     * Basic OAuth fields
     *
     * @param  string  $consumerKey
     * @return array
     */
    public static function getBaseOauthFields($consumerKey)
    {
        /**
         * Build unique nonce
         * https://www.php.net/manual/en/function.com-create-guid.php#99425
         */
        $nonce = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );

        return [
            'oauth_consumer_key'     => $consumerKey,
            'oauth_nonce'            => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_version'          => '1.0',
        ];
    }

    /**
     * Sign request
     *
     * @param  string  $set
     * @param  string  $url
     * @param  array   $fields
     * @param  string  $secret
     * @return string
     */
    public static function sign($set, $url, $fields, $secret)
    {
        $url = urlencode($url);

        ksort($fields);

        $fields = urlencode(http_build_query($fields));
        $data   = "$set&$url&$fields";

        self::dbg('SIGN', '>', $data);

        // Get binary and encode afterwards
        $hash = hash_hmac('sha1', $data, $secret, true);
        $hash = base64_encode($hash);

        self::dbg('SIGN', '<', $hash);

        return $hash;
    }

    /**
     * GET via curl
     *
     * @param  string         $url
     * @param  array|string   $fields
     * @param  array          $headers
     * @return string
     */
    public static function curlGet($url, $fields = null, $headers = null)
    {
        return self::curlFetch(false, $url, $fields, $headers);
    }

    /**
     * POST via curl
     *
     * @param  string        $url
     * @param  array|string  $fields
     * @param  array         $headers
     * @return string
     */
    public static function curlPost($url, $fields = null, $headers = null)
    {
        return self::curlFetch(true, $url, $fields, $headers);
    }

    // --------------------------------------------------------------------
    // PROTECTED
    // --------------------------------------------------------------------

    /**
     * Build OAuth header from fields
     *
     * @param  array   $fields
     * @return string
     */
    protected static function OAuthHeader($fields)
    {
        $auth = [];

        foreach ($fields as $key => $value) {
            // Only parameters starting with "oauth_" are relevant!
            if (preg_match('~^oauth_~', $key)) {
                $auth[] = sprintf('%s="%s"', $key, $value);
            }
        }

        $auth = 'Authorization: OAuth ' . implode(',', $auth);

        self::dbg('HEAD', '-', $auth);

        return $auth;
    }

    // --------------------------------------------------------------------
    // PRIVATE
    // --------------------------------------------------------------------

    private $consumerKey;
    private $consumerSecret;
    private $token;
    private $tokenSecret;

    /**
     * Debugger
     *
     * @param  string  $method
     * @return void
     */
    private static function dbg($method)
    {
        $args   = func_get_args();
        $method = array_shift($args);

        $args = array_map(function ($arg) {
            return is_scalar($arg) ? $arg : json_encode($arg);
        }, $args);

        self::$debug[] = sprintf('%-4s %s', $method, implode(' ', $args));
    }

    /**
     * Fetch data via cUrl
     *
     * @param  bool          $isPOST
     * @param  string        $url
     * @param  array|string  $fields
     * @param  array         $headers
     * @return string
     */
    private static function curlFetch($isPOST, $url, $fields, $headers)
    {
        $ch = curl_init();

        $method = $isPOST ? 'POST' : 'GET';

        self::dbg($method, '>', $url);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            self::dbg($method, '> Headers:', $headers);
        }

        self::dbg($method, '> Fields:', $fields);

        if (!$isPOST) {
            if ($fields) {
                if (is_array($fields)) {
                    $fields = http_build_query($fields);
                }
                $url .= '?' . $fields;
            }
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $ts  = -microtime(true);
        $res = curl_exec($ch);
        $ts += microtime(true);

        self::$curlInfo[] = curl_getinfo($ch);

        curl_close($ch);

        self::dbg($method, '< curl', round($ts * 1000, 3), 'ms');
        self::dbg($method, '<', $res);

        return $res;
    }
}
