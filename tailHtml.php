<?php

/*
TODO
    Double output of randNumberForStartMArker?
        fixed but now needs first on own
*/

/**
 * Debug function to dump a variable to a log file.
 *
 * @param mixed $Capture The variable to dump.
 * @param int $File The file number to write to (1 or 2). Defaults to 1.
 * @param bool $first Whether this is the first entry in the log file. Defaults to false.
 */
function bwdebug($Capture, $File = 1, $first = false) {

    // -------------
    $tabItOut = 1;
    $genRandNumberForStartMarker = 1;
    // -------------
var_dump($Capture);
    $output = "\n-";

    if ($File == 1) {
        $filename = 'output.log';
    } else {
        $filename = 'output2.log';
    }

    if ($first) {
        if ($genRandNumberForStartMarker) {
            $output .= str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        }
        $output .= "-----".date('H:i:s')."------------\n";
    } else {
        if (is_string($Capture) && strpos($Capture, '**') === 0) {
            // is method header (starts with **)
            if ($tabItOut) {
                $capStrs = explode("#", $Capture);
                foreach ($capStrs as $capStr)
                {
                    $output .= $capStr."\t";
                }

                // trim the ** used to detect method headers
                $output = str_replace('**', '', $output);

                // trim the last tab
                $output = rtrim($output, "\t")."\n";
            }
        } else {
            ob_start();
            var_dump($Capture);
            $output = ob_get_clean();
        }
    }

    file_put_contents($filename, $output, FILE_APPEND);
}

bwdebug("stringy", 1, 1);
bwdebug("stringy");
bwdebug(1);

$birds = ['blue', 'tit', 'pigeon'];
$fruit = ['apple', 'bannana', 'pear'];
$cars = ['fird', 'pergeot', 'vauxhall'];

//bwdebug("", 1, 1);
//bwdebug($birds, 1, 1);
//bwdebug(["fruit", $fruit]);
//bwdebug($cars, 2);
//bwdebug($birds, 2, true);
//bwdebug (dirname(__FILE__)."#".basename(__FILE__)."#".__FUNCTION__);

genRandHtml(1);

// while (true)
// {
//     file_put_contents('output.log', genRandHtml(10), FILE_APPEND);
//     sleep(2);
// }

function genRandHtml($num_lines) {
    bwdebug("**".dirname(__FILE__)."#".basename(__FILE__)."#".__CLASS__."#->".__FUNCTION__."():".__LINE__);
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
    bwdebug($lines);
    //var_dump($lines);    return implode("\n", $lines);
    //return $lines;
}


?>