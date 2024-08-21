<?php

function main(array $args) : array
{
    $filePath = 'main/src/CMasterLibrary.php';
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
