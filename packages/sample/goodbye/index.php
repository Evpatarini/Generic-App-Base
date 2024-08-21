<?php
require_once(dirname(__FILE__).'/../../../src/CMasterLibrary.php');
function main(array $args) : array
{
  echo "We in here instead";
  return [
        'body' => CMasterLibrary::goodbyeOutput(),
    ];
}
?>
