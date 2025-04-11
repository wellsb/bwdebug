<?php

/*
TODO
    $first outputs it's variable to the console (twice actually) instead of the file

    look into;
        xdebug.cli_color = 0
        xdebug.overload_var_dump

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
 * @param mixed $capture The variable to dump.
 * @param int $file The file number to write to (1 or 2). Defaults to 1.
 * @param bool $first Whether this is the first entry in the log file (output debug header). Defaults to false.
 */
function bwdebug($capture, int $file = 1, bool $first = false) {

    // -Config------

    // output debug files
    // work
        //$defaultOutputFile = '/var/www/bwtools/logs/output.log';
        //$secondOutputFile = '/var/www/bwtools/logs/output2.log';
        //$stateFile = '/var/www/bwtools/state.json';
    // dev
        $defaultOutputFile = '/var/www/html/dev/bwdebug2/logs/output.log';
        $secondOutputFile = '/var/www/html/dev/bwdebug2/logs/output2.log';
        $stateFile = '/var/www/html/dev/bwdebug2/state.json';

    // Output method var_dump || print_r || var_export
    //$outputMethod = 'var_dump';
    $stripTagsFromVarDumpOutput = true;
    $outputMethod = 'print_r';
    //$outputMethod = 'var_export';

    // Tab out method headers
    $tabOutMethodHeaders = true;

    // Start each capture with a random number
    $genRandNumberForStartMarker = 1;

    // Blank lines before output
    $blankLinesBetweenOutputs	= 1;	// lines or 0 to disable
    $blankLinesBeforeAllOutputs = 5;	// lines or 0 to disable
    $allOutputTimeOut		= 3;	// seconds

    // Try to output coloured outputs
    $colourOutput = 1;
    $colourDebugHeaders = true;
    $debugHeaderColor = "\033[32m";
    $colourMethodHeaders = true;
    $methodHeaderColour = "\033[33m";
    $colourActualOutput = true;
    $actualOutputColour = "\033[36m";

    // Debug to raw $Capture to StdOut
    $debugToStdOut = 0;

    // Overide xdebug.var_'s
    $overrideXdebugIni = true;

    // -------------

    if ($overrideXdebugIni) {
        ini_set('xdebug.var_display_max_depth', 15);
        ini_set('xdebug.var_display_max_children', 100);
    }

    // Work out what file to send this output
    if ($file == 1) {
        $filename = $defaultOutputFile;
    } else {
        $filename = $secondOutputFile;
    }

    // Debug to console
    if ($debugToStdOut) {
        var_dump($capture);
    }

    $output = "";
    $state = readState($stateFile);
    //var_dump($state);

    // Do some blank lines before all outputs
    if ($blankLinesBeforeAllOutputs > 0) {
        $timeElapsed = time() - $state->lastStarted;

        if ($timeElapsed >= $allOutputTimeOut) {
            $output .= "\n: ".$timeElapsed;
            for ($i = 1; $i <= $blankLinesBeforeAllOutputs; $i++) {
                $output .= "\n";
            }
        }
    }
    $state->lastStarted = time();

    if ($first) {
        // Turn the colour on if needed
        if ($colourOutput && $colourDebugHeaders) {
            $output .= $debugHeaderColor;
        }

        // Add random number to start marker
        if ($genRandNumberForStartMarker) {
            $output .= "-".str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT)."-----".date('H:i:s')."------------\n";
        }

        // Turn the colour off again if needed
        if ($colourOutput && $colourDebugHeaders) {
            $output .= "\033[0m";
        }
    }

    // Is not first (could be method header or normal dump)
    if (is_string($capture) && strpos($capture, '**') === 0) {
        // It's a method header (starts with **)
        if ($tabOutMethodHeaders) {
            $capStrs = explode("#", $capture);

            // Colour on?
            if ($colourOutput && $colourMethodHeaders) {
                $output .= $methodHeaderColour;
            }

            foreach ($capStrs as $capStr)
            {
                $output .= $capStr."\t";
            }

            // If colour on? turn it off
            if ($colourOutput && $colourMethodHeaders) {
                $output .= "\033[0m";
            }

            // Trim the ** used to detect method headers
            $output = str_replace('**', '', $output);

            // Trim the last tab
            $output = rtrim($output, "\t")."\n";
        } else {
            // None tabbed method header
            // Colour on?
            //echo "\nNone tabbed method header";
            if ($colourOutput && $colourMethodHeaders) {
                $output .= "\033[33m";
            }

            $output .= $capture."\n";
            // Trim the ** used to detect method headers
            $output = str_replace('**', '', $output);
            // Replace # for something else
            $output = str_replace('#', ' ', $output);

            // If colour on? turn it off
            if ($colourOutput && $colourMethodHeaders) {
                $output .= "\033[0m";
            }
        }
    } else {
        // Default output
        ob_start();
        if ($outputMethod == 'var_dump')
        {
            var_dump($capture);
        } else if ($outputMethod == 'print_r')
        {
            print_r($capture);
        }

        if (!$first) {
            $output = ob_get_clean();
        }

        // If colour actual output is on
        if ($colourOutput && $colourActualOutput) {
            $output = $actualOutputColour.$output."\033[0m";
        }
    }

    // If set insert blank lines
    if ($blankLinesBetweenOutputs > 0) {
        for ($i = 1; $i <= $blankLinesBetweenOutputs; $i++) {
            $output .= "\n";
        }
    }

    if ($outputMethod == 'var_dump' && $stripTagsFromVarDumpOutput) {
        $output = strip_tags($output);
        $output = str_replace('=&gt; ', '=> ', $output);
        $output = str_replace('&quot;', '"', $output);
    }

    file_put_contents($filename, $output, FILE_APPEND);
    saveState($stateFile, $state);
}

function saveState($stateFile, $state) {
    // Save state to file
    if (!file_put_contents($stateFile, json_encode($state))) {
        echo "\nERROR: Could not write state file";
        return true;
    } else {
        return false;
    }
}

function readState($stateFile) {
    // Read state data from the file
    if (file_exists($stateFile)) {
        if (!$state = file_get_contents($stateFile)) {
            echo "\nERROR: Could not read from state file";
            return false;
        } else {
            $state = json_decode($state);
        }
    } else {
        echo "\nERROR: State file missing on read";
        return false;
    }

    return $state;
}

// Test arrays
$birds = ['blue', 'tit', 'pigeon'];
$fruit = ['apple', 'bannana', 'pear'];
$cars = ['fird', 'pergeot', 'vauxhall'];


//bwdebug("a string", 1, 1);
//bwdebug("another string");
bwdebug($birds);
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
    //bwdebug($lines);
    //var_dump($lines);    return implode("\n", $lines);
    //return $lines;
}


?>
