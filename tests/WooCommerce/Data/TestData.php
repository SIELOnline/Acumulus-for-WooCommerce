<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Data;

/**
 * TestData allows access to test data.
 */
class TestData
{
    /**
     * Returns test data, typically a created a completed invoice converted to an array.
     *
     * @param string $data
     *
     * @return mixed
     *   The json decoded testdata.
     *
     * @throws \RuntimeException
     * @throws \JsonException
     */
    public function get(string $data)
    {
        $filename = __DIR__ . "/$data.json";
        $json = file_get_contents($filename);
        if ($json === false) {
            throw new \RuntimeException("Could not read test data $data");
        }
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
