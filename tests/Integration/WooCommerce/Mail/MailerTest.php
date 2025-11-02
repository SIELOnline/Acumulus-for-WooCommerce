<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Mail;

use MockPHPMailer;
use Siel\Acumulus\Tests\WooCommerce\TestCase;
use WP_PHPMailer;

/**
 * MailerTest tests whether the mailer class mails messages to the mail server.
 *
 * This test is mainly used to test if the mail feature still works in new versions of the
 * shop.
 */
class MailerTest extends TestCase
{
    public function testMailer(): void
    {
        global $phpmailer;
        $oldPHPMailer = $phpmailer;
        if ($phpmailer instanceof MockPHPMailer) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
            $phpmailer = new WP_PHPMailer(true);
            $phpmailer::$validator = static function ( $email ) {
                return (bool) is_email( $email );
            };
        }
        $this->_testMailer(false);
        $phpmailer = $oldPHPMailer;
    }
}
