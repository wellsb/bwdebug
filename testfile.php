<?php

require_once('bwdebug2.php');

// Test arrays
$birds = ['blue', 'tit', 'pigeon'];
$fruit = ['apple', 'bannana', 'pear'];
$cars = ['fird', 'pergeot', 'vauxhall'];

bwdebug("£££-", 1, 1);
//bwdebug("a string", 1, 1);
//bwdebug("another string");
bwdebug($birds);
bwdebug(1);
//bwdebug("", 1, 1);
//bwdebug($birds, 1, 1);
//bwdebug(["fruit", $fruit], 2);
//bwdebug($cars, 2);
//bwdebug($birds, 2, true);

genRandHtml(1);

// test function
function genRandHtml($num_lines) {
    bwdebug("**".dirname(__FILE__)."#".basename(__FILE__)."#".__FUNCTION__."():".__LINE__);
    //bwdebug("sakldjh");
    $lines = [];
    for ($i = 0; $i < $num_lines; $i++) {
        $random_element = rand(1, 3);
        switch ($random_element) {
            case 1:
                $lines[] =  "<h1>This is a random heading</h1>";
                break;
            case 2:
                $lines[] = "<p>This is a random paragraph</p>";
                break;
            case 3:
                $lines[] = "<ul><li>This is a random list item</li><li>This is another random list item</li></ul>";
                break;
        }
    }
    bwdebug(["lines ", $lines]);
    //var_dump($lines);    return implode("\n", $lines);
    //return $lines;
}