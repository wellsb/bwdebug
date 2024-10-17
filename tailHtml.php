<?php

/*
TODO
    Double output of randNumberForStartMArker?
        fixed but now needs first on own
*/

/**
 * Debug function to dump variables to a log file.
 * 
 * output "some string" to default file     = bwdebug("some string");
 * output labelled array to default file    = bwdebug(["fruit", $fruit]);
 * output "a string" to file 2 with header  = bwdebug("a string", 2, 1);
 * labelled array to file 2                 = bwdebug(["fruit", $fruit], 2);
 * output "a string" to File 1 with header  = bwdebug("a string", 1, 1);
 * output int(1) to default file            = bwdebug(1);
 *
 * @param mixed $Capture The variable to dump.
 * @param int $File The file number to write to (1 or 2). Defaults to 1.
 * @param bool $first Whether this is the first entry in the log file (output debug header). Defaults to false.
 */
function bwdebug($Capture, $File = 1, $first = false) {

    // -Config------
        // Tab out method headers
        $tabItOut = 1;

        // Output method var_dump || print_r
        $outputMethod = 'var_dump';

        // Start each capture with a random number
        $genRandNumberForStartMarker = 1;

        // Blank lines before output
        $blankLinesBetweenOutputs = 1;

        // Try to output coloured outputs
        $colouredOutput = true;
            $colouredDebugHeaders = true;
            $colourMethodHeaders = true;

        // Debug to raw $Capture to StdOut
        $debugToStdOut = true;
    // -------------

    // Debug to console
    if ($debugToStdOut) {
        var_dump($Capture);
    }

    $output = "";

    // If set insert blank lines
    if ($blankLinesBetweenOutputs > 0) {
        for ($i = 0; $i <= $blankLinesBetweenOutputs; $i++) {
            $output .= "\n";
        }
    }

    // Work out what file to send this output
    if ($File == 1) {
        $filename = 'output.log';
    } else {
        $filename = 'output2.log';
    }


    if (!$first) {
        // Is not first (could be method header or normal dump)
        if (is_string($Capture) && strpos($Capture, '**') === 0) {
            // It's a method header (starts with **)
            if ($tabItOut) {
                $capStrs = explode("#", $Capture);
                foreach ($capStrs as $capStr)
                {
                    if ($colouredOutput && $colourMethodHeaders) {
                        $output .= "\033[33m".$capStr."\t \033[0m";
                    } else {
                        $output .= $capStr."\t";
                    }
                }

                // Trim the ** used to detect method headers
                $output = str_replace('**', '', $output);

                // Trim the last tab
                $output = rtrim($output, "\t")."\n";
            } else {
                // None tabbed method header
                $output = $Capture;
            }
        } else {
            // Default output
            ob_start();
            if ($outputMethod == 'var_dump')
            {
                var_dump($Capture);
            } else if ($outputMethod == 'print_r')
            {
                print_r($Capture);
            }
            $output = ob_get_clean();
        }
    } else {
        // Is first - print debug header
        if ($colouredOutput && $colouredDebugHeaders) {
            $output .= "\033[32m";
        }

        if ($genRandNumberForStartMarker) {
            $output .= "-".str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT)."-----".date('H:i:s')."------------\n";
        }

        if ($colouredOutput && $colouredDebugHeaders) {
            $output .= "\033[0m";
        }
        $output .= $Capture;




    }
    file_put_contents($filename, $output, FILE_APPEND);
}

// Test arrays
$birds = ['blue', 'tit', 'pigeon'];
$fruit = ['apple', 'bannana', 'pear'];
$cars = ['fird', 'pergeot', 'vauxhall'];


bwdebug("a string", 1, 1);
//bwdebug("another string");
//bwdebug(1);
//bwdebug("", 1, 1);
//bwdebug($birds, 1, 1);
//bwdebug(["fruit", $fruit], 2);
//bwdebug($cars, 2);
//bwdebug($birds, 2, true);
//bwdebug (dirname(__FILE__)."#".basename(__FILE__)."#".__FUNCTION__);

genRandHtml(1);

// while (true)
// {
//     file_put_contents('output.log', genRandHtml(10), FILE_APPEND);
//     sleep(2);
// }

// test function
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