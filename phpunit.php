<?php

require __DIR__ . '/SimpleAR.php';

$cfg = new SimpleAR\Config();
$cfg->doForeignKeyWork = true;
$cfg->debug            = true;
$cfg->dateFormat       = 'd/m/Y';
$cfg->foreignKeySuffix = 'Id';
$cfg->aliases = array();

$sar = new SimpleAR($cfg);

require __DIR__ . '/tests/models.php';
