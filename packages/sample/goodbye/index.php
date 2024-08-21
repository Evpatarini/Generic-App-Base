<?php
namespace main\packages\sample\goodbye;
use main\src\CMasterLibrary;
function main(array $args) : array
{
  require_once(dirname(__FILE__).'/../../../src/CMasterLibrary.php');
  echo "We in here instead";
  return [
        'body' => CMasterLibrary::goodbyeOutput(),
    ];
}
?>
