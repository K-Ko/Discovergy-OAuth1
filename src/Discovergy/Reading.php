<?php
/**
 *
 */
namespace Discovergy;

/**
 *
 */
class Reading
{
    /**
     * Class constructor
     *
     * @param object|null $reading
     */
    public function __construct($reading)
    {
        if (is_object($reading)) {
            // Put time into data for consistent __get() handling
            $this->data = [ 'time' => $reading->time ] + json_decode(json_encode($reading->values), true);
        }
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

        if ($name == 'power_kw' && isset($this->data['enpowerergy'])) {
            return $this->data['power'] / 1e6;
        }

        // RLM power
        if ($name == 'power_w' && isset($data->{'21.25'}, $data->{'41.25'}, $data->{'61.25'})) {
            return $data->{'21.25'} + $data->{'41.25'} + $data->{'61.25'} / 1e3;
        }

        if ($name == 'power_kw' && isset($data->{'21.25'}, $data->{'41.25'}, $data->{'61.25'})) {
            return ($data->{'21.25'} + $data->{'41.25'} + $data->{'61.25'}) / 1e6;
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

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /**
     * @var array
     */
    private $data = [];
}
