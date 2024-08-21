<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
function main(array $args) : array
{
  require_once(dirname(__FILE__).'/../../../src/CMasterLibrary.php');
  echo "We in here instead";
  return [
        'body' => CMasterLibrary::goodbyeOutput(),
    ];
}
?>
