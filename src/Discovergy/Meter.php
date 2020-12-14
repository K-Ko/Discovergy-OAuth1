<?php
/**
 *
 */
namespace Discovergy;

/**
 *
 */
use JsonSerializable;

/**
 *
 */
class Meter implements JsonSerializable
{
    /**
     * Class constructor
     *
     * @param \Discovergy\API1 $api
     * @param object $data
     */
    public function __construct(API1 $api, object $data)
    {
        $this->api  = $api;
        $this->data = json_decode(json_encode($data), true);
    }

    /**
     * Magic getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'address') {
            $l = $this->data['location'];

            // Remove city district
            $l['city'] = preg_replace('~ *([(]|OT) +.*$~', '', $l['city']);

            return sprintf('%s %s, %s-%s %s', $l['street'], $l['streetNumber'], $l['country'], $l['zip'], $l['city']);
        }

        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Call GET endpoints for a meter
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
            $endpoint  = strtolower(trim(preg_replace('~[A-Z]~', '_$0', $matches[1]), '_'));
            // meterId is required
            $params = [ 'meterId' => $this->data['meterId'] ];

            if (isset($arguments[0]) && is_array($arguments[0])) {
                $params = array_merge($params, $arguments[0]);
            }

            return $this->api->get($endpoint, $params);
        }

        throw new BadMethodCallException('Invalid method call: ' . __CLASS__ . ':' . $name . '()');
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        $data = $this->data;

        $data['address'] = $this->address;

        ksort($data);

        return $data;
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /**
     * @var \Discovergy\API1
     */
    private $api;

    /**
     * @var array
     */
    private $data = [];
}
