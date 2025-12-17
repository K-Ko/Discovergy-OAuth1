<?php

namespace OAuth1;

use DateTimeImmutable;
use Exception;

/**
 * OAuth 1.0 session
 */
final class Session
{
    /**
     * @var array Debug messages
     */
    public $debug = [];

    /**
     * @var array
     */
    public $curlInfo = [];

    /**
     * Class constructor
     *
     * @param string $baseUrl
     */
    public function __construct(string $baseUrl)
    {
        $this->setBaseUrl($baseUrl);

        // Don't cache by default
        $this->setCache()->setTTL(0);

        $this->curl = curl_init();
    }

    /**
     * Authorize client
     *
     * @throws \Exception On any kind of error
     *
     * @param  string $client
     * @param  string $identifier
     * @param  string $secret
     * @return void
     */
    public function authorize($client, $identifier, $secret)
    {
        if ($this->ttl > 0) {
            // Unique hash for actual identifier, all requests can share the OAuth session
            $cache = $this->cache . '/.oauth.' . substr(md5($identifier), 0, 16) . '.json';

            // OAuth data, force re-reread if needed
            is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

            if (is_file($cache)) {
                // Simple array
                [$this->consumerKey, $this->consumerSecret, $this->token, $this->tokenSecret] =
                    json_decode(file_get_contents($cache), true);

                $this->dbg('AUTH', ':', 'Authorization from cache');

                return;
            }
        }

        $this->dbg('AUTH', ':', 'Authorize');

        // ------------------------------------------------------------------
        // 1. Get consumer token
        // ------------------------------------------------------------------

        $url    = $this->baseUrl . '/oauth1/consumer_token';
        $fields = ['client' => $client];
        $res    = json_decode($this->fetchPost($url, $fields), true);

        if (!isset($res['key'], $res['secret'])) {
            throw new Exception('Get customer token failed (1) ' . json_encode($this->getLastCurlInfo()));
        }

        $consumerKey    = $res['key'];
        $consumerSecret = $res['secret'];

        // ------------------------------------------------------------------
        // 2. Get request token
        // ------------------------------------------------------------------

        $url    = $this->baseUrl . '/oauth1/request_token';
        $fields = $this->getBaseOauthFields($consumerKey);

        $fields['oauth_signature'] = $this->sign('POST', $url, $fields, $consumerSecret . '&');

        $res = $this->fetchPost($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_token'], $res['oauth_token_secret'])) {
            throw new Exception('Get request token failed (2) ' . json_encode($this->getLastCurlInfo()));
        }

        // Internal used for steps 3 & 4
        $token       = $res['oauth_token'];
        $tokenSecret = $res['oauth_token_secret'];

        // ------------------------------------------------------------------
        // 3. Authorize user
        // ------------------------------------------------------------------

        $url    = $this->baseUrl . '/oauth1/authorize';
        $fields = ['oauth_token' => $token, 'email' => $identifier, 'password' => $secret];

        $res = $this->fetchGet($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_verifier'])) {
            throw new Exception('Authorize user failed (3) ' . json_encode($this->getLastCurlInfo()));
        }

        $oauthVerifier = $res['oauth_verifier'];

        // ------------------------------------------------------------------
        // 4. Get access token
        // ------------------------------------------------------------------

        $url = $this->baseUrl . '/oauth1/access_token';

        $fields = $this->getBaseOauthFields($consumerKey);
        $fields['oauth_token']     = $token;
        $fields['oauth_verifier']  = $oauthVerifier;

        $fields['oauth_signature'] = $this->sign('POST', $url, $fields, $consumerSecret . '&' . $tokenSecret);

        $res = $this->fetchPost($url, $fields);

        parse_str($res, $res);

        if (!isset($res['oauth_token'], $res['oauth_token_secret'])) {
            throw new Exception('Get access token failed (4) ' . json_encode($this->getLastCurlInfo()));
        }

        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->token          = $res['oauth_token'];
        $this->tokenSecret    = $res['oauth_token_secret'];

        // Save if a cache file name is given
        $this->ttl > 0 && file_put_contents($cache, json_encode($this->getSecrets()));

        $this->dbg('AUTH', ':', 'Authorized');
    }

    /**
     * Needed for cache credentials extern for reuse
     *
     * @return array
     */
    public function getSecrets()
    {
        return [$this->consumerKey, $this->consumerSecret, $this->token, $this->tokenSecret];
    }

    /**
     * Get last cUrl info data
     */
    public function getLastCurlInfo()
    {
        return count($this->curlInfo) ? $this->curlInfo[count($this->curlInfo) - 1] : null;
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
        $fields = $this->getBaseOauthFields($this->consumerKey);
        $fields = array_replace($fields, $params);

        $fields['oauth_token'] = $this->token;
        $fields['oauth_signature'] = $this->sign(
            'GET',
            $url,
            $fields,
            $this->consumerSecret . '&' . $this->tokenSecret
        );

        return $this->fetchGet($url, $params, ['Content-Type: application/json', $this->OAuthHeader($fields)]);
    }

    /**
     * Set $baseUrl
     *
     * @param  string $baseUrl
     * @return \OAuth1\Session
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Set cache, creates directory if not exist
     *
     * @throws Exception In case of invalid directory
     *
     * @param  string $cache Use system temp. directory if empty
     * @return \OAuth1\Session
     */
    public function setCache(string $cache = ''): self
    {
        if ($cache == '') {
            $this->cache = sys_get_temp_dir();
        } else {
            if (!is_dir($cache) && !mkdir($cache, 0755, true)) {
                throw new Exception('Can not create cache: ' . $cache);
            }

            if (!is_dir($cache)) {
                throw new Exception('Invalid cache: ' . $cache);
            }

            $this->cache = $cache;
        }

        return $this;
    }

    /**
     * Get cache directory
     *
     * @return string
     */
    public function getCache(): string
    {
        return $this->cache;
    }

    /**
     * Set cache TTL
     *
     * @param  int $ttl seconds
     * @return \OAuth1\Session
     */
    public function setTTL(int $ttl): self
    {
        // No negative values
        $this->ttl = max(0, $ttl);

        return $this;
    }

    /**
     * Get cache TTL seconds
     *
     * @return int
     */
    public function getTTL(): int
    {
        return $this->ttl;
    }

    // --------------------------------------------------------------------
    // PRIVATE
    // --------------------------------------------------------------------

    /**
     * base API URL
     *
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $consumerKey;

    /**
     * @var string
     */
    private $consumerSecret;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $tokenSecret;

    /**
     * @var string
     */
    private $cache;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var \CurlHandle|ressource
     */
    private $curl;

    /**
     * Debugger
     *
     * @param  string  $method
     * @param  mixed   ...$args
     * @return void
     */
    private function dbg($method, ...$args)
    {
        $this->debug[] = sprintf(
            '[%s] %-4s %s',
            (new DateTimeImmutable())->format('H:i:s.u'),
            $method,
            implode(
                ' ',
                array_map(function ($arg) {
                    return is_scalar($arg) ? $arg : json_encode($arg);
                }, $args)
            )
        );
    }

    /**
     * Build OAuth header from fields
     *
     * @param  array   $fields
     * @return string
     */
    private function OAuthHeader($fields)
    {
        $auth = [];

        foreach ($fields as $key => $value) {
            // Only parameters starting with "oauth_" are relevant!
            if (preg_match('~^oauth_~', $key)) {
                $auth[] = sprintf('%s="%s"', $key, $value);
            }
        }

        $auth = 'Authorization: OAuth ' . implode(',', $auth);

        $this->dbg('HEAD', '-', $auth);

        return $auth;
    }

    /**
     * Basic OAuth fields
     *
     * @param  string  $consumerKey
     * @return array
     */
    private function getBaseOauthFields($consumerKey)
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
     * @param  string  $method
     * @param  string  $url
     * @param  array   $fields
     * @param  string  $secret
     * @return string
     */
    private function sign($method, $url, $fields, $secret)
    {
        $url = urlencode($url);

        ksort($fields);

        $fields = urlencode(http_build_query($fields));
        $data   = "$method&$url&$fields";

        $this->dbg('SIGN', '>', $data);

        // Get binary and encode afterwards
        $hash = hash_hmac('sha1', $data, $secret, true);
        $hash = base64_encode($hash);

        $this->dbg('SIGN', '<', $hash);

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
    private function fetchGet(string $url, $fields = null, array $headers = [])
    {
        return $this->fetch('GET', $url, $fields, $headers);
    }

    /**
     * POST via curl
     *
     * @param  string        $url
     * @param  array|string  $fields
     * @param  array         $headers
     * @return string
     */
    private function fetchPost(string $url, $fields = null, array $headers = [])
    {
        return $this->fetch('POST', $url, $fields, $headers);
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
    private function fetch(string $method, string $url, $fields, array $headers): string
    {
        curl_reset($this->curl);

        $this->dbg($method, '>', $url);

        if (!empty($headers)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
            $this->dbg($method, '> Headers:', $headers);
        }

        $this->dbg($method, '> Fields:', $fields);

        if ($method !== 'POST') {
            if ($fields) {
                if (is_array($fields)) {
                    $fields = http_build_query($fields);
                }
                $url .= '?' . $fields;
            }
        } else {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        $ts  = -microtime(true);
        $res = curl_exec($this->curl);
        $ts += microtime(true);

        $info = curl_getinfo($this->curl);

        $this->curlInfo[] = $info;

        $this->dbg($method, '< curl', round($ts * 1000, 3), 'ms');
        $this->dbg($method, '<', $res);
        $this->dbg($method, '<', $info);

        return $res;
    }
}
