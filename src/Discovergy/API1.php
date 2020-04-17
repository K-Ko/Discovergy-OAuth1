<?php
/**
 *
 */
namespace KKo\Discovergy;

/**
 *
 */
use Exception;
use BadMethodCallException;
use KKo\OAuth1\Session;

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
     * @param string   $oauthCacheFile
     * @param integer  $oauthTTL
     * @param string   $meterCacheFile
     * @param integer  $meterTTL
     */
    public function __construct(
        $client,
        $identifier,
        $secret,
        $oauthCacheFile = '',
        $oauthTTL = 0,
        $meterCacheFile = '',
        $meterTTL = 0
    ) {
        // Clear file status cache
        clearstatcache();

        // OAuth data
        if (is_file($oauthCacheFile) && filemtime($oauthCacheFile) < time() - $oauthTTL) {
            // Force re-read
            unlink($oauthCacheFile);
        }

        if (is_file($oauthCacheFile)) {
            // Simple array
            $secrets = json_decode(file_get_contents($oauthCacheFile));
            static::$session = new Session(...$secrets);
        } else {
            $tries = 0;

            while (true) {
                try {
                    $tries++;
                    static::$session = Session::authorize($client, $identifier, $secret);
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
            if ($oauthCacheFile) {
                file_put_contents($oauthCacheFile, json_encode(static::$session->getSecrets()));
            }
        }

        $this->meterCacheFile = $meterCacheFile;
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
            if ($this->meterCacheFile) {
                if (is_file($this->meterCacheFile) && filemtime($this->meterCacheFile) < time() - $meterTTL) {
                    // Force re-read
                    unlink($this->meterCacheFile);
                }
            }

            if (is_file($this->meterCacheFile)) {
                $this->meters = json_decode(file_get_contents($this->meterCacheFile));
            } else {
                $loop = 0;

                do {
                    $meters = static::$session->get($this->baseUrl . '/meters');

                    if (!empty($meters)) {
                        // Save if a cache file name is given
                        $this->meterCacheFile && file_put_contents($this->meterCacheFile, $meters);

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

        $meterCount = count($meters);

        foreach ($meters as &$m) {
            // Check posible fields for a meter Id
            if ($m->serialNumber === $meterId || $m->fullSerialNumber === $meterId || $m->meterId === $meterId) {
                return $m;
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
    private $meterCacheFile = '';

    /**
     * @var array
     */
    private $meters = [];
}
