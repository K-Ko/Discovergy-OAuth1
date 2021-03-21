<?php
/**
 *
 */
namespace Discovergy;

/**
 *
 */
use Exception;
use BadMethodCallException;
use OAuth1\Session;

/**
 * API OAuth 1.0
 */
final class API1
{
    /**
     * Discovergy API base URL
     *
     * @var string
     */
    public static $baseUrl = 'https://api.discovergy.com/public/v1';

    /**
     * OAuth1 session
     *
     * @var \OAuth1\Session
     */
    public static $session;

    /**
     * Add some headers
     *
     * @var boolean
     */
    public static $debug = false;

    /**
     * Class constructor
     *
     * @param string   $client
     * @param string   $identifier
     * @param string   $secret
     */
    public function __construct(string $client, string $identifier, string $secret)
    {
        // Clear file status cache
        clearstatcache();

        // Discovergy credentials
        $this->client     = $client;
        $this->identifier = $identifier;
        $this->secret     = $secret;

        // Cache defaults to system temp. dir and TTL of 1 hour
        $this->cache = sys_get_temp_dir();
        $this->ttl   = 3600;

        // Unique hash for actual identifier, all requests can share the OAuth session
        $this->hash  = substr(md5($identifier), 0, 16);
    }

    /**
     * Init connection, authorize
     *
     * @return \Discovergy\API1
     */
    public function init(): API1
    {
        $cache = $this->cache . '/.oauth.' . $this->hash . '.json';

        // OAuth data, force re-reread if needed
        is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

        $lock  = $this->cache . '/.oauth.' . $this->hash . '.lock';
        $locks = 0;

        while (file_exists($lock)) {
            // Another process gets authorization from Discovergy
            usleep(100000); // 100 ms
            $locks++;
        }

        static::$debug && header('X-OAUTH-AUTHORIZATION-LOCKS: ' . $locks);

        if (is_file($cache)) {
            // Simple array
            static::$session = new Session(... json_decode(file_get_contents($cache)));
            static::$debug && header('X-OAUTH-AUTHORIZATION-CACHE: true');

            return $this;
        }

        // Start authorization
        touch($lock);

        // Handle parallel requests, only one have to try to authorize!
        $loop = 0;

        // Endles loop, break condition inside
        do {
            try {
                static::$session = Session::authorize($this->client, $this->identifier, $this->secret);
                break; // Success, break while
            } catch (Exception $e) {
                if ($loop < 5) {
                    // Sleep a bit to give API chance to answer
                    sleep(++$loop);
                    continue;
                }

                unlink($lock);
                throw new Exception('Session creation failed, ' . $e->getMessage());
            }
        } while (true);

        // Save if a cache file name is given
        file_put_contents($cache, json_encode(static::$session->getSecrets()));

        // Unlock
        unlink($lock);

        return $this;
    }

    /**
     * Set cache or TTL via setters
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function __set($name, $value)
    {
        if ($name === 'cache') {
            $this->setCache($value);
        } elseif ($name === 'ttl') {
            $this->setTTL($value);
        }
    }

    /**
     * Set cache, creates directory if not exist
     *
     * @throws Exception In case of invalid directory
     * @param string $cache Use system temp. directory if empty
     * @return \Discovergy\API1
     */
    public function setCache(string $cache): API1
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
     * @param int $ttl seconds
     * @return \Discovergy\API1
     */
    public function setTTL(int $ttl): API1
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

    /**
     * Read all known meters from DC
     *
     * @return \Discovergy\Meters
     */
    public function getMeters(): Meters
    {
        // Lazy load
        if (!$this->meters) {
            $cache = $this->cache . '/.meters.' . $this->hash . '.json';

            // Force re-reread if needed
            is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

            $lock  = $this->cache . '/.meters.' . $this->hash . '.lock';
            $locks = 0;

            while (file_exists($lock)) {
                // Another process gets authorization from Discovergy
                usleep(100000); // 100 ms
                $locks++;
            }

            static::$debug && header('X-OAUTH-METER-LOCKS: ' . $locks);

            if (is_file($cache)) {
                $meters = json_decode(file_get_contents($cache));
                static::$debug && header('X-OAUTH-METER-CACHE: true');
            } else {
                // Start authorization
                touch($lock);

                $loop = 0;

                do {
                    if (!empty($meters = $this->get('meters'))) {
                        file_put_contents($cache, json_encode($meters));
                        break;
                    }

                    // Sleep a bit to give API chance to answer
                    sleep(++$loop);
                } while ($loop < 5);

                // Unlock
                unlink($lock);
            }

            $this->meters = new Meters();

            foreach ($meters as $meter) {
                $this->meters[] = new Meter($this, $meter);
            }
        }

        return $this->meters;
    }

    /**
     * Get meter details
     *
     * @param  string $meterId
     * @return \Discovergy\Meter|null
     */
    public function getMeter($meterId)
    {
        // Lazy load meters on request
        $meters = $this->getMeters();

        return $meters[$meterId];
    }

    /**
     * Generic GET caller
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function get(string $endpoint, array $params = [])
    {
        return json_decode(static::$session->get(static::$baseUrl . '/' . $endpoint, $params));
    }

    // --------------------------------------------------------------------
    // PRIVATE
    // --------------------------------------------------------------------

    /**
     * @var string
     */
    private $client;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var string
     */
    private $cache;

    /**
     * @var \Discovergy\Meters
     */
    private $meters;
}
