<?php
$cfg['Servers'][1]['host'] = getenv('PMA_HOST') ?: 'mysql';
$cfg['Servers'][1]['port'] = getenv('PMA_PORT') ?: '3306';
$cfg['Servers'][1]['compress'] = false;
$cfg['Servers'][1]['AllowNoPassword'] = false;

$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['TempDir'] = '/tmp';
$cfg['MaxRows'] = 50;
$cfg['SendErrorReports'] = 'never';

$cfg['ThemeManager'] = true;
$cfg['ThemeDefault'] = 'boodark';
