<?php

require __DIR__ . '/SimpleAR.php';

$cfg = new SimpleAR\Config();
$cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/tests/db.json'), true);
$cfg->doForeignKeyWork = true;
$cfg->debug            = true;
$cfg->modelDirectory   = __DIR__ . '/tests/models/';
$cfg->dateFormat       = 'd/m/Y';
$cfg->foreignKeySuffix = 'Id';

$sar = new SimpleAR($cfg);

require __DIR__ . '/tests/models.php';
