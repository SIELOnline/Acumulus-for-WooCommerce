<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Helpers;

use Automattic\WooCommerce\Internal\Admin\Logging\FileV2\File;
use Automattic\WooCommerce\Internal\Admin\Logging\Settings;
use Siel\Acumulus\Tests\WooCommerce\TestCase;

use function sprintf;

/**
 * LogTest tests whether the log class logs messages to a log file.
 *
 * This test is mainly used to test if the log feature still works in new versions of the
 * shop.
 */
class LogTest extends TestCase
{
    private function getLogFolder(): string
    {
        return Settings::get_log_directory();
    }

    protected function getLogPath(): string
    {
        $fileId = File::generate_file_id('acumulus', null, time());
        return sprintf('%s%s-%s.log', $this->getLogFolder(), $fileId, File::generate_hash($fileId));
    }

    public function testLog(): void
    {
        $this->_testLog();
    }
}
