<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function main(array $args) : array
{
    $filePath = dirname(__FILE__).'/../../../src/CMasterLibrary.php';
    $sBody = 'Including file at: ' . $filePath . "\n";

    if (file_exists($filePath)) {
        require_once $filePath;
        $sBody .=  'File included successfully.' . "\n";
    } else {
        $sBody .=  'File not found.' . "\n";
    }
  return [
        'body' => $sBody ,
    ];
}
?>
