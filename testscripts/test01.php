<?php

// A one directory deep test script

require_once ('anotherclass.php');

require_once(__DIR__ . '/../bwdebug2.php');

timer_start('test timer');

bwdebug('first', 'first label', 1, 0); // Last arg is includeTrace

bwdebug('test script one directory deep');
bwdebug('test script one directory deep', 'label', 2);

$Avariable = 'klajslkjshd689076';

bwdebug($Avariable, "A Variable");

class TestThings {
    public function genRandHtml2(int $num_lines): array
    {bwdebug(null, null, 1, false, true);
        //bwdebug("**" . __DIR__ . "#" . basename(__FILE__) . "#" . __FUNCTION__ . "():" . __LINE__);

        $lines = [];
        $elements = [
            "<h1>This is a random heading</h1>",
            "<p>This is a random paragraph</p>",
            "<ul><li>Random list item 1</li><li>Random list item 2</li></ul>"
        ];

        for ($i = 0; $i < $num_lines; $i++) {
            $lines[] = $elements[array_rand($elements)];
        }

        $hexNumbers = $this->genRandomHex(3);

        $lines['hexs'] = $hexNumbers;

        bwdebug($lines,'Generated HTML Lines');
        return $lines;
    }

    public function genRandomHex(int $num_hex_numbers): array
    {bwdebug(null, null, 1, false, true);
        //bwdebug("**" . __DIR__ . "#" . basename(__FILE__) . "#" . __FUNCTION__ . "():" . __LINE__);
        bwdebug('null', 'stack', 1, 1); // Last arg is includeTrace
        $hexNumbers = [];
        for ($i = 0; $i < $num_hex_numbers; $i++) {
            $hexNumbers[] = sprintf('%06X', mt_rand(0, 0xFFFFFF));
        }



        bwdebug($hexNumbers, 'Generated Hex Numbers');
        return $hexNumbers;
    }
}

$test = new TestThings();
$test->genRandHtml2(3);

$anotherclass = new anotherclass();
$anotherclass->outputRandomLetters(5);

bwdebug('After function call');

timer_end('test timer');