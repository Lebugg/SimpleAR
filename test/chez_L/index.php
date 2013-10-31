<?php
date_default_timezone_set('Europe/Paris');

require 'C:/Program Files/wamp/www/SimpleAR/lib/SimpleAR.php';

$oCfg = SimpleAR\Config::instance();
$oCfg->dsn = array(
	'driver' => 'mysql',
	'host' => 'localhost',
	'name' => 'simplear_L',
	'user' => 'root',
	'password' => '',
);
$oCfg->autoRetrieveModelColumns = true;
$oCfg->foreignKeySuffix         = '_id';
$oCfg->modelDirectory           = dirname(__FILE__) . '/models/';

SimpleAR\Model::init();

$oCompany = Company::create(array('name' => 'Ma belle entreprise'));

$aOffers = array();
$aOffers[] = new Offer(array('name' => 'Première offre'));
$aOffers[] = new Offer(array('name' => 'Deuxième offre'));

$oCompany->offers = $aOffers;
$oCompany->save();

var_dump($oCompany->offers);

$oCompany->delete('offers');

var_dump($oCompany);

$oCompany->delete();