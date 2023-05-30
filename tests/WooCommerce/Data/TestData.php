<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Data;

use Siel\Acumulus\Helpers\Log;

/**
 * TestData allows access to test data.
 */
class TestData
{
    /**
     * Returns test data, typically a created and completed invoice converted to an array.
     *
     * @return mixed|null
     *   The json decoded testdata, or null if the file does not yet exist.
     *
     * @throws \JsonException
     */
    public function get(string $type, int $id)
    {
        $filename = __DIR__ . "/$type$id.json";
        if (!is_readable($filename)) {
            return null;
        }
        $json = file_get_contents($filename);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Saves test data, typically a created and completed invoice converted to an array.
     *
     * @param mixed $data
     *   The data to be saved (in json format).
     */
    public function save(string $type, int $id, bool $isNew, $data): void
    {
        $append = $isNew ? '' : '.latest';
        $filename = __DIR__ . "/$type$id$append.json";
        file_put_contents($filename, json_encode($data, Log::JsonFlags));
    }
}
