<?php
 
function main(array $args) : array
{
    $name = $args["name"] ?? "stranger";
    
    $greeting = "Hello {$name}! Youre a Dummy";
    echo $greeting;
 
    return [
        'body' => $greeting,
    ];
}
