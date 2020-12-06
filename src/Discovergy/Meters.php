<?php
/**
 *
 */
namespace Discovergy;

/**
 *
 */
use ArrayAccess;
use Iterator;

/**
 *
 */
class Meters implements ArrayAccess, Iterator
{
    /**
     * ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->meters[] = $value;
        } else {
            $this->meters[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        foreach ($this->meters as $meter) {
            if ($meter->fullSerialNumber === $offset ||
                substr($meter->fullSerialNumber, -8) === substr($offset, -8) ||
                $meter->serialNumber === $offset ||
                $meter->meterId === $offset) {
                return true;
            }
        }

        return false;
    }

    public function offsetUnset($offset)
    {
        unset($this->meters[$offset]);
    }

    public function offsetGet($offset)
    {
        foreach ($this->meters as $meter) {
            if ($meter->fullSerialNumber === $offset ||
                $meter->serialNumber === $offset ||
                $meter->meterId === $offset) {
                return $meter;
            }
        }

        return null;
    }

    /**
     * Iterator
     */
    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->meters[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        $this->position++;
    }

    public function valid()
    {
        return isset($this->meters[$this->position]);
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /**
     * @var integer
     */
    private $position = 0;

    /**
     * @var array
     */
    private $meters = [];
}
