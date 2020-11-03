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
class API1
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
        $this->client       = $client;
        $this->identifier   = $identifier;
        $this->secret       = $secret;
    }

    /**
     * Init connection, authorize
     *
     * @return KKo\Discovergy\API1
     */
    public function init(): API1
    {
        // Clear file status cache
        clearstatcache();

        $cache = $this->cache ? $this->cache . '/.oauth.' . md5($this->client . $this->identifier) : false;

        // OAuth data, force re-reread if needed
        $cache && is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

        if (is_file($cache)) {
            // Simple array
            $secrets = json_decode(file_get_contents($cache));
            static::$session = new Session(...$secrets);
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
            if ($cache) {
                file_put_contents($cache, json_encode(static::$session->getSecrets()));
            }
        }

        return $this;
    }

    /**
     * Set cache handling
     *
     * @param string|bool $dir
     * @return KKo\Discovergy\API1
     */
    public function setCache($cache = true): API1
    {
        if ($cache === true) {
            $this->cache = sys_get_temp_dir();
        } elseif ($cache === false || $cache == '') {
            $this->cache = false;
        } else {
            $this->cache = $cache;
            is_dir($this->cache) || mkdir($this->cache, 0755, true);
        }

        return $this;
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl
     * @return KKo\Discovergy\API1
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
        if (empty($this->meters)) {
            $cache = $this->cache ? $this->cache . '/.meters.' . md5($this->client . $this->identifier) : false;

            // Force re-reread if needed
            $cache && is_file($cache) && filemtime($cache) < time() - $this->ttl && unlink($cache);

            if (is_file($cache)) {
                $this->meters = json_decode(file_get_contents($cache));
            } else {
                $loop = 0;

                do {
                    $meters = static::$session->get($this->baseUrl . '/meters');

                    if (!empty($meters)) {
                        // Save if a cache file name is given
                        $cache && file_put_contents($cache, $meters);

                        $meters = json_decode($meters);

                        if (json_last_error() == JSON_ERROR_NONE) {
                            $this->meters = $meters;
                            break;
                        }
                    }

                    // Sleep a bit to give API chance to answer
                    sleep(++$loop);
                } while (!$meters || $loop < 5);
            }
        }

        return $this->meters;
    }

    /**
     * Get meter details
     *
     * @param  string $meterId
     * @return \stdClass
     */
    public function getMeter($meterId)
    {
        // Lazy load meters on request
        $meters = $this->getMeters();

        if (!$meters || !is_array($meters)) {
            return null;
        }

        foreach ($meters as &$meter) {
            // Check posible fields for a valid meter Id
            if ($meter->fullSerialNumber === $meterId ||
                $meter->serialNumber === $meterId ||
                $meter->meterId === $meterId) {
                return $meter;
            }
        }
    }

    /**
     * Call GET endpoints
     *
     * Metadata
     * - /devices
     * - /field_names
     * Measurements
     * - /readings
     * - /last_reading
     * - /statistics
     * - /load_profile
     * - /raw_load_profile
     * Disaggregation
     * - /disaggregation
     * - /activities
     * Website Access Code
     * - /website_access_code
     * Virtual meters
     * - /virtual_meters
     *
     * @throws BadMethodCallException
     * @param  string $name Method called
     * @param  array  $arguments Method arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (preg_match('~^get(.*)$~', $name, $matches)) {
            // Endpoint name from CamelCase to snake_case
            $endpoint = strtolower(trim(preg_replace('~[A-Z]~', '_$0', $matches[1]), '_'));

            if (!isset($arguments[0]) || !is_array($arguments[0])) {
                throw new BadMethodCallException('Required: ' . __CLASS__ . '::' . $name . '(array $params)');
            }

            return $this->get($endpoint, $arguments[0]);
        }

        throw new BadMethodCallException('Invalid method call: ' . __CLASS__ . '::' . $name . '()');
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
     * @var array
     */
    private $meters = [];
}
