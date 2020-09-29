<?php
ini_set('display_errors', 'on');
require_once dirname(__DIR__) . '/bootstrap.php';

use Jtl\Connector\Client\ConnectorClient;

$connectorToken = 'abcdefg';
$connectorUrl = 'http://dev.lan/shopware6-connector/public/?XDEBUG_SESSION_START=PHPSTORM';

$client = new ConnectorClient($connectorToken, $connectorUrl);

$category = (new \Jtl\Connector\Core\Model\Category('', 1))
    ->setSort(2)
    ->addI18n((new \Jtl\Connector\Core\Model\CategoryI18n())
        ->setName('First')
        ->setDescription('First try')
        ->setTitleTag('title')
        ->setMetaDescription('meta description en')
        ->setMetaKeywords('meta, keywords, en')
        ->setLanguageIso('en')
    )->addI18n((new \Jtl\Connector\Core\Model\CategoryI18n())
        ->setName('erste')
        ->setDescription('Erster Versuch')
        ->setTitleTag('titel yo yo yo')
        ->setMetaDescription('erste echte meta beschreibung')
        ->setMetaKeywords('first, erst, soweiter')
        ->setLanguageIso('de')
    );

print_r($client->push('category', [$category]));

//print_r($client->identify());