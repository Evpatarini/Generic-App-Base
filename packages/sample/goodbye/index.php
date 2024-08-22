<?php
require __DIR__ . '/vendor/autoload.php';
use \src\CMasterLibrary;
function main(array $args) : array
{
    $filePath = '/src/CMasterLibrary.php';
    $sBody = 'Including file at: ' . $filePath . "\n";

    if (file_exists($filePath)) {
        require_once $filePath;
        $sBody .=  'File included successfully.' . "\n";
    } else {
        $sBody .=  'File not found.' . "\n";
    }
    $sBody .= $_SERVER['SCRIPT_NAME'];
  return [
        'body' => $sBody ,
    ];
}
?>
