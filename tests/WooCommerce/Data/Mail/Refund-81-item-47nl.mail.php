<?php
/**
 * @noinspection GrazieInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

$mail =
    [
        'from' => 'erwin@burorader.com',
        'fromName' => 'WooCommerce Acumulus Tests',
        'to' => 'erwin@burorader.com',
        'subject' => 'Voorraadmutatie verzonden naar Acumulus: succes',
        'bodyText' => [
            'Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd.

Over de voorraadmutatie:

Bestelling:                81
Bestelregel:               47
Product (Webwinkel):       woo-vneck-tee-green
Mutatie:                   +3
Product (Acumulus):        1833637
Voorraadniveau (Acumulus): ',
            '
Verzendresultaat:          2 - "Succes"

Informatie voor Acumulus support:

De informatie hieronder wordt alleen getoond om eventuele support te
vergemakkelijken, u kunt deze informatie negeren. U kunt support
contacteren door deze mail door te sturen naar
woocommerce@acumulus.nl.

• Request: uri=https://api.sielsystems.nl/acumulus/stable/stock/stock_add.php
submit={
    "contract": {
        "contractcode": "288252",
        "username": "APIGebruiker12345",
        "password": "REMOVED FOR SECURITY",
        "emailonerror": "plugins@siel.nl",
        "emailonwarning": "plugins@siel.nl"
    },
    "format": "json",
    "testmode": 0,
    "lang": "nl",
    "connector": {
        "application": "WooCommerce ',
            '"sourceuri": "https://github.com/SIELOnline/libAcumulus"
    },
    "stock": {
        "productid": 1833637,
        "stockamount": 3.0,
        "stockdescription": "localhost bestelling 81",
        "meta-match-shop-value": "woo-vneck-tee-green",
        "meta-acumulus-product-id-source": "remote",
        "meta-match-shop-field": "[product::getShopObject()::get_sku()]",
        "meta-match-acumulus-field": "productsku"
    }
}
• Response: status=200
body={
    "stock": {
        "stockamount": "',
            '",
        "productid": "1833637"
    },
    "errors": {
        "count_errors": "0"
    },
    "warnings": {
        "count_warnings": "0"
    },
    "status": "0"
}
',
        ],
        'bodyHtml' => [
            '<p>Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd.</p>
<h3>Over de voorraadmutatie</h3>
<table style="text-align: left;">
<tr><th>Bestelling</th><td>81</td></tr>
<tr><th>Bestelregel</th><td>47</td></tr>
<tr><th>Product (Webwinkel)</th><td><a href="http://localhost/woocommerce-acumulus-tests/wp-admin/post.php?post=30&action=edit">woo-vneck-tee-green</a></td></tr>
<tr><th>Mutatie</th><td>+3</td></tr>
<tr><th>Product (Acumulus)</th><td>1833637</td></tr>
<tr><th>Voorraadniveau (Acumulus)</th><td>',
            '</td></tr>
<tr><th>Verzendresultaat</th><td>2 - "Succes"</td></tr>
</table>
<h3>Informatie voor Acumulus support</h3>
<p>De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.
U kunt support contacteren door deze mail door te sturen naar woocommerce@acumulus.nl.</p>
<details><summary><span>(klik om te tonen of te verbergen)</span></summary><ul>
<li><span>Request: uri=https://api.sielsystems.nl/acumulus/stable/stock/stock_add.php<br>
submit={<br>
    "contract": {<br>
        "contractcode": "288252",<br>
        "username": "APIGebruiker12345",<br>
        "password": "REMOVED FOR SECURITY",<br>
        "emailonerror": "plugins@siel.nl",<br>
        "emailonwarning": "plugins@siel.nl"<br>
    },<br>
    "format": "json",<br>
    "testmode": 0,<br>
    "lang": "nl",<br>
    "connector": {<br>
        "application": "WooCommerce ',
            '"sourceuri": "https://github.com/SIELOnline/libAcumulus"<br>
    },<br>
    "stock": {<br>
        "productid": 1833637,<br>
        "stockamount": 3.0,<br>
        "stockdescription": "localhost bestelling 81",<br>
        "meta-match-shop-value": "woo-vneck-tee-green",<br>
        "meta-acumulus-product-id-source": "remote",<br>
        "meta-match-shop-field": "[product::getShopObject()::get_sku()]",<br>
        "meta-match-acumulus-field": "productsku"<br>
    }<br>
}</span></li>
<li><span>Response: status=200<br>
body={<br>
    "stock": {<br>
        "stockamount": "',
            '",<br>
        "productid": "1833637"<br>
    },<br>
    "errors": {<br>
        "count_errors": "0"<br>
    },<br>
    "warnings": {<br>
        "count_warnings": "0"<br>
    },<br>
    "status": "0"<br>
}</span></li></ul>
</details>
',
        ],
    ];
