<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\TestCase;
use Siel\Acumulus\Mail\Mailer;
use WC_Order_Item_Product;
use WC_Product;

/**
 * ProductManagerTest tests the end-to-end execution of stock management events.
 */
class ProductManagerTest extends TestCase
{
    private function getLog(): Log
    {
        return self::getContainer()->getLog();
    }

    private function getMailer(): Mailer
    {
        return static::getContainer()->getMailer();
    }

    public static function orderProvider(): array
    {
        return [
            'Order 81 item 45' => [Source::Order, 81, 45],
            'Order 81 item 46' => [Source::Order, 81, 46],
            'Order 81 item 47' => [Source::Order, 81, 47],
            'Refund 81 item 45' => [Source::CreditNote, 82, 45],
            'Refund 81 item 46' => [Source::CreditNote, 82, 46],
            'Refund 81 item 47' => [Source::CreditNote, 82, 47],
        ];
    }

    /**
     * This test method is loosely based on {@see \wc_reduce_stock_levels()} and
     * {@see \wc_increase_stock_levels()} that reduce respectively increase stock levels
     * for all item lines in an order/refund.
     *
     * @dataProvider orderProvider
     */
    public function testStockManagement(string $sourceType, int $sourceId, int $itemId): void
    {
        $source = static::getContainer()->createSource($sourceType, $sourceId);
        $orderSource = $source->getOrder();
        foreach ($orderSource->getItems() as $item) {
            if ($item->getId() === $itemId) {
                break;
            }
        }
        /** @var \Siel\Acumulus\WooCommerce\Invoice\Item $item */
        $config = static::getContainer()->getConfig();
        $debug = $config->set('debug', Config::Send_SendAndMail);
        try {
            if ($sourceType === Source::Order) {
                $this->reduceStockLevels($item->getShopObject());
            } else {
                $this->increaseStockLevels($item->getShopObject());
            }
        } finally {
            $config->set('debug', $debug);
        }
    }

    private function reduceStockLevels(WC_Order_Item_Product $item): void
    {
        static::assertTrue($item->is_type('line_item'));
        $product = $item->get_product();
        static::assertInstanceOf(WC_Product::class, $product);
        if (!$product->managing_stock()) {
            return;
        }

        $mailCount = $this->getMailer()->getMailCount();
        $new_stock = 100;
        $change = ['product' => $product, 'from' => $new_stock + $item->get_quantity(), 'to' => $new_stock];
        do_action('woocommerce_reduce_order_item_stock', $item, $change, $item->get_order());
        $this->checkLog();
        $this->checkMail($mailCount);
    }

    private function increaseStockLevels(WC_Order_Item_Product $item): void
    {
        static::assertTrue($item->is_type('line_item'));
        $product = $item->get_product();
        static::assertInstanceOf(WC_Product::class, $product);
        if (!$product->managing_stock()) {
            return;
        }

        $mailCount = $this->getMailer()->getMailCount();
        $new_stock = 100;
        $old_stock = $new_stock - $item->get_quantity();
        do_action('woocommerce_restore_order_item_stock', $item, $new_stock, $old_stock, $item->get_order());
        $this->checkLog();
        $this->checkMail($mailCount);
    }

    /**
     * Checks the log messages.
     */
    public function checkLog(): void
    {
        $loggedMessages = $this->getLog()->getLoggedMessages();
        $logMessage = end($loggedMessages);

        // dataName() returns the key of the actual data set.
        $name = str_replace(' ', '-', $this->dataName()) . static::getContainer()->getLanguage();
        $this->assertLogMatches($name, $logMessage);
    }

    /**
     * Checks the mail messages.
     */
    public function checkMail(int $mailCount): void
    {
        if ($this->getMailer()->getMailCount() > $mailCount) {
            static::assertSame($mailCount + 1, $this->getMailer()->getMailCount());
            $mailSent = $this->getMailer()->getMailSent($mailCount);
            static::assertIsArray($mailSent);

            // dataName() returns the key of the actual data set.
            $name = str_replace(' ', '-', $this->dataName()) . static::getContainer()->getLanguage();
            $this->assertMailMatches($name, $mailSent);
        }
    }
}
