<?php

declare(strict_types=1);

use Siel\Acumulus\Tests\CopyLatest;

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * CopyLatest copies WooCommerce {type}{id}.latest.json test data to {type}{id}.json.
 */
class CopyLatestWc extends CopyLatest
{
    public function execute(): void
    {
        $this->run(__DIR__ . '/Integration/WooCommerce/Data');
    }
}

(new CopyLatestWc())->execute();
