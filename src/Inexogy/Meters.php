<?php

namespace Inexogy;

use ArrayAccess;
use Iterator;
use JsonSerializable;

class Meters implements ArrayAccess, Iterator, JsonSerializable
{
    /**
     * ArrayAccess
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->meters[] = $value;
        } else {
            $this->meters[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        foreach ($this->meters as $meter) {
            // phpcs:ignore
            if (
                $meter->fullSerialNumber === $offset ||
                substr($meter->fullSerialNumber, -8) === substr($offset, -8) ||
                $meter->serialNumber === $offset ||
                $meter->meterId === $offset
            ) {
                return true;
            }
        }

        return false;
    }

    public function offsetUnset($offset): void
    {
        unset($this->meters[$offset]);
    }

    public function offsetGet($offset)
    {
        foreach ($this->meters as $meter) {
            // phpcs:ignore
            if (
                $meter->fullSerialNumber === $offset ||
                substr($meter->fullSerialNumber, -8) === substr($offset, -8) ||
                $meter->serialNumber === $offset ||
                $meter->meterId === $offset
            ) {
                return $meter;
            }
        }

        return null;
    }

    /**
     * Iterator
     */
    public function rewind(): void
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

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return isset($this->meters[$this->position]);
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->meters;
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /**
     * @var integer
     */
    private $position = 0;

    /**
     * @var \Inexogy\Meter[]
     */
    private $meters = [];
}
