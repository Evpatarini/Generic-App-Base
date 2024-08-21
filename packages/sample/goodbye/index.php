<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('sample-functions-php-helloworld/src/CMasterLibrary.php');
function main(array $args) : array
{
  echo CMasterLibrary::goodbyeOutput();
  return [
        'body' => 'WAHHHHHHHHH',
    ];
}
?>
