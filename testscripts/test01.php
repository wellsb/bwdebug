<?php

// A one directory deep test script

require_once('/var/www/html/dev/bwdebug2/bwdebug2.php');

timer_start('test timer');

bwdebug('test script one directory deep');

$Avariable = 'klajslkjshd689076';

bwdebug($Avariable, "My Variable");

genRandHtml2(1);

function genRandHtml2(int $num_lines): array
{bwdebug("**" . __DIR__ . "#" . basename(__FILE__) . "#" . __FUNCTION__ . "():" . __LINE__);

    $lines = [];
    $elements = [
        "<h1>This is a random heading</h1>",
        "<p>This is a random paragraph</p>",
        "<ul><li>Random list item 1</li><li>Random list item 2</li></ul>"
    ];

    for ($i = 0; $i < $num_lines; $i++) {
        $lines[] = $elements[array_rand($elements)];
    }

    // Use bwdebug to show the result before returning
    bwdebug(['Generated HTML Lines', $lines]);
    return $lines;
}

bwdebug('After function call');
bwdebug('something', null, 2, false, true);

timer_end('test timer');