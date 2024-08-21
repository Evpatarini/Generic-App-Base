<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once(dirname(__FILE__).'/../../../src/CMasterLibrary.php');
function main(array $args) : array
{
  echo "We in here instead";
  return [
        'body' => 'WOOOOOOOOOO',//CMasterLibrary::goodbyeOutput(),
    ];
}
?>
