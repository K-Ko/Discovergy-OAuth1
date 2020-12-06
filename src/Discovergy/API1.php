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
    public $baseUrl = 'https://api.discovergy.com/public/v1';

    /**
     * OAuth1 session
     *
     * @var \KKo\OAuth1\Session
     */
    public static $session;

    /**
     * Class constructor
     *
     * @param string   $client
     * @param string   $identifier
     * @param string   $secret
     */
    public function __construct($client, $identifier, $secret)
    {
        $this->client     = $client;
        $this->identifier = $identifier;
        $this->secret     = $secret;
    }

    /**
     * Init connection, authorize
     *
     * @return Discovergy\API1
     */
    public function init(): API1
    {
        // Clear file status cache
        clearstatcache();

        $cache = $this->cache
               ? $this->cache . '/.oauth.' . md5($this->client . $this->identifier) . '.json'
               : false;

        // OAuth data, force re-reread if needed
        $cache && is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

        if (is_file($cache)) {
            // Simple array
            $secrets = json_decode(file_get_contents($cache));
            static::$session = new Session(... $secrets);
        } else {
            $tries = 0;

            // Endles loop, break condition inside
            while (true) {
                try {
                    $tries++;
                    static::$session = Session::authorize($this->client, $this->identifier, $this->secret);
                    break; // Success, break while
                } catch (Exception $e) {
                    if ($tries < 5) {
                        // Sleep a bit to give API chance to answer
                        sleep($tries);
                    } else {
                        throw new Exception('Session creation failed, ' . $e->getMessage());
                    }
                }
            }

            // Save if a cache file name is given
            $cache && file_put_contents($cache, json_encode(static::$session->getSecrets()));
        }

        return $this;
    }

    /**
     * Set cache handling
     *
     * @param string|bool $dir
     * @return Discovergy\API1
     */
    public function setCache($cache = true): API1
    {
        if ($cache === true) {
            $this->cache = sys_get_temp_dir();
            $this->ttl   = 86400;
        } elseif ($cache === false || $cache == '') {
            $this->cache = false;
            $this->ttl   = 0;
        } else {
            $this->cache = $cache;
            is_dir($this->cache) || mkdir($this->cache, 0755, true);
            $this->ttl = 86400;
        }

        return $this;
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl
     * @return Discovergy\API1
     */
    public function setTTL($ttl): API1
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Read all known meters from DC
     *
     * @return array
     */
    public function getMeters()
    {
        // Lazy load
        if (!$this->meters) {
            $cache = $this->cache
                   ? $this->cache . '/.meters.' . md5($this->client . $this->identifier) . '.json'
                   : false;

            // Force re-reread if needed
            $cache && is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

            if (is_file($cache)) {
                $meters = json_decode(file_get_contents($cache));
            } else {
                $loop = 0;

                do {
                    if (!empty($meters = $this->get('meters'))) {
                        // Save if a cache file name is given
                        $cache && file_put_contents($cache, json_encode($meters));
                        break;
                    }

                    // Sleep a bit to give API chance to answer
                    sleep(++$loop);
                } while (!$meters || $loop < 5);
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
     * @return stdClass
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
    public function get($endpoint, array $params = [])
    {
        return json_decode(static::$session->get($this->baseUrl . '/' . $endpoint, $params));
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
    private $cache = false;

    /**
     * @var Discovergy\Meters
     */
    private $meters;
}
