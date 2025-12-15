<?php

namespace Inexogy;

use JsonSerializable;

class Reading implements JsonSerializable
{
    /**
     * Class constructor
     *
     * @param object $reading Reading data from Inexogy endpoint /readings
     */
    public function __construct($reading)
    {
        if (is_object($reading)) {
            // Put time into data for consistent __get() handling and convert values to array
            $this->data = ['time' => $reading->time] + json_decode(json_encode($reading->values), true);
        }
    }

    /**
     * Make Readings instances from readings objects
     *
     * @param array $readings
     * @return array
     */
    public static function toReadings(array $readings): array
    {
        $result = [];

        foreach ($readings as $reading) {
            $result[] = new static($reading);
        }

        return $result;
    }

    /**
     * Magic getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'timestamp') {
            return floor($this->data['time'] / 1000);
        }

        if ($name == 'datetime') {
            return date('Y-m-d H:i:s', floor($this->data['time'] / 1000));
        }

        if ($name == 'datetime_ms') {
            // Timestamp with ms
            $ts = $this->data['time'] / 1000;
            $ms = sprintf('%03d', round(($ts - floor($ts)) * 1000));
            return date('Y-m-d H:i:s.', $ts) . $ms;
        }

        // power
        if ($name == 'power_w' && isset($this->data['power'])) {
            return $this->data['power'] / 1e3;
        }

        if ($name == 'power_kw' && isset($this->data['power'])) {
            return $this->data['power'] / 1e6;
        }

        // RLM power
        if ($name == 'power' && isset($this->data['21.25'], $this->data['41.25'], $this->data['61.25'])) {
            return $this->data['21.25'] + $this->data['41.25'] + $this->data['61.25'];
        }

        if ($name == 'power_w' && isset($this->data['21.25'], $this->data['41.25'], $this->data['61.25'])) {
            return $this->data['21.25'] + $this->data['41.25'] + $this->data['61.25'] / 1e3;
        }

        if ($name == 'power_kw' && isset($this->data['21.25'], $this->data['41.25'], $this->data['61.25'])) {
            return ($this->data['21.25'] + $this->data['41.25'] + $this->data['61.25']) / 1e6;
        }

        // energy
        if ($name == 'energy_wh' && isset($this->data['energy'])) {
            return $this->data['energy'] / 1e7;
        }

        if ($name == 'energy_kwh' && isset($this->data['energy'])) {
            return $this->data['energy'] / 1e10;
        }

        // energyOut
        if ($name == 'energyOut_wh' && isset($this->data['energyOut'])) {
            return $this->data['energyOut'] / 1e7;
        }

        if ($name == 'energyOut_kwh' && isset($this->data['energyOut'])) {
            return $this->data['energyOut'] / 1e10;
        }

        // RLM energy
        if ($name == 'energy' && isset($this->data['1.8.0'])) {
            return $this->data['1.8.0'];
        }

        if ($name == 'energy_wh' && isset($this->data['1.8.0'])) {
            return $this->data['1.8.0'] / 1e3;
        }

        if ($name == 'energy_kwh' && isset($this->data['1.8.0'])) {
            return $this->data['1.8.0'] / 1e6;
        }

        // RLM energyOut
        if ($name == 'energyOut' && isset($this->data['2.8.0'])) {
            return $this->data['2.8.0'];
        }

        if ($name == 'energyOut_wh' && isset($this->data['2.8.0'])) {
            return $this->data['2.8.0'] / 1e3;
        }

        if ($name == 'energyOut_kwh' && isset($this->data['2.8.0'])) {
            return $this->data['2.8.0'] / 1e6;
        }

        // existing keys
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        $data = $this->data;

        $data['timestamp']      = $this->timestamp;
        $data['datetime']       = $this->datetime;
        $data['power']          = $this->power;         // Re-read to catch RLMs
        $data['power_w']        = $this->power_w;
        $data['power_kw']       = $this->power_kw;
        $data['energy']         = $this->energy;        // Re-read to catch RLMs
        $data['energy_wh']      = $this->energy_wh;
        $data['energy_kwh']     = $this->energy_kwh;
        $data['energyOut']      = $this->energyOut;     // Re-read to catch RLMs
        $data['energyOut_wh']   = $this->energyOut_wh;
        $data['energyOut_kwh']  = $this->energyOut_kwh;

        ksort($data);

        return $data;
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /**
     * @var array
     */
    private $data = [];
}
