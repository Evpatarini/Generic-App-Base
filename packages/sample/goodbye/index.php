<?php

function main(array $args) : array
{
    $filePath = __DIR__.'/../../../src/CMasterLibrary.php';
    $sBody = 'Including file at: ' . $filePath . "\n".__DIR__;

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
