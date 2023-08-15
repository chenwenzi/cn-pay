<?php

$get = print_r($_GET, true);
$post = print_r($_POST, true);
$head1 = 'get: '.$_SERVER['REQUEST_URI'] . PHP_EOL;
$head2 = 'post: '.$_SERVER['REQUEST_URI'] . PHP_EOL;

echo '<pre>';

print_r($get);

print_r($post);