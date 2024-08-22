<?php

function main(array $args) : array
{
    $filePath = '/../../CHTML.php';
    $sBody = 'Including file at: ' . $filePath . "\n";

    if (file_exists($filePath)) {
        require_once $filePath;
        $sBody .=  'File included successfully.' . "\n";
    } else {
        $sBody .=  'File not found.' . "\n";
    }
    $oHTML = new CHTML();
    $sBody .= $oHTML->divTextArea('What','Why','When',array());
  return [
        'body' => $sBody ,
    ];
}
?>
