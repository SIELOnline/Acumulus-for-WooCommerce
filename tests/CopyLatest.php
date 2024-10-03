<?php
/**
 * @noinspection PhpIllegalPsrClassPathInspection
 */

declare(strict_types=1);

use Siel\Acumulus\Tests\WooCommerce\AcumulusTestUtils;

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

/**
 * CopyLatest copies {type}{id}.latest.json test data to {type}{id}.json.
 */
class CopyLatest
{
    use AcumulusTestUtils;

    public function execute(): void
    {
        $this->copyLatestTestSources();
    }
}

(new CopyLatest())->execute();
