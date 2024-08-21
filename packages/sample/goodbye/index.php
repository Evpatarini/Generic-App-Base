<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('/src/CMasterLibrary.php');
function main(array $args) : array
{
  echo "We in here instead";
  return [
        'body' => CMasterLibrary::goodbyeOutput(),
    ];
}
?>
