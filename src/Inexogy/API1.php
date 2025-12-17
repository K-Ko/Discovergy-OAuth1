<?php

namespace Inexogy;

use Exception;
use InvalidArgumentException;

/**
 * API OAuth 1.0
 */
final class API1
{
    /**
     * Class constructor
     *
     * @param \OAuth1\Session $session
     */
    public function __construct($session, string $baseUrl = 'https://api.inexogy.com/public/v1')
    {
        $this->session = $session;
        $this->baseUrl = $baseUrl;

        // Unique hash for the OAuth1 session
        $this->hash = substr(md5(json_encode($session->getSecrets())), 0, 16);

        // Don't cache by default
        $this->setCache()->setTTL(0);

        // Clear file status cache
        clearstatcache();
    }

    /**
     * Get session
     *
     * @return \OAuth1\Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set cache, creates directory if not exist
     *
     * @throws Exception In case of invalid directory
     * @param string $cache Use system temp. directory if empty
     * @return \Inexogy\API1
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
     * @param int $ttl seconds
     * @return \Inexogy\API1
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

    /**
     * Read all known meters from DC
     *
     * @return \Inexogy\Meters
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

            if (is_file($cache)) {
                $meters = json_decode(file_get_contents($cache));
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
     * @throws \InvalidArgumentException For unknown meter Id
     *
     * @param  string $meterId
     * @return \Inexogy\Meter
     */
    public function getMeter($meterId)
    {
        // Lazy load meters on request
        $meters = $this->getMeters();

        if (!isset($meters[$meterId])) {
            throw new InvalidArgumentException('Unbekannte Zählernummer: ' . $meterId);
        }

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
        return json_decode($this->session->get($this->baseUrl . '/' . $endpoint, $params));
    }

    // --------------------------------------------------------------------
    // PRIVATE
    // --------------------------------------------------------------------

    /**
     * OAuth1 session
     *
     * @var \OAuth1\Session
     */
    private $session;

    /**
     * Inexogy API base URL
     *
     * @var string
     */
    private $baseUrl;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var string
     */
    private $cache;

    /**
     * @var \Inexogy\Meters
     */
    private $meters;
}
