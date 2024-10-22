<?php

/*
TODO
    $first outputs it's variable to the console (twice actually) instead of the file

    var_dump prettyfier;
    You can switch off Xdebug-var_dump()-overloading by setting xdebug.overload_var_dump to false. Then you can use var_dump() when you don't need the additional HTML-formatting and xdebug_var_dump() when you require a fully formatted debug output.
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
        //$defaultOutputFile = '/var/www/bwdebug2/logs/output.log';
        //$defaultOutputFile = '/var/www/bwdebug2/logs/output.log';
        $defaultOutputFile = '/media/170/html/dev/bwdebug2/logs/output.log';
        $secondOutputFile = '/media/170/html/dev/bwdebug2/logs/output2.log';

        // Output method var_dump || print_r || var_export
            $outputMethod = 'var_dump';
				$stripTagsFromVarDumpOutput = false;
                ini_set("xdebug.overload_var_dump", "off");
            //$outputMethod = 'print_r';
			//$outputMethod = 'var_export';

        // Tab out method headers
            $tabOutMethodHeaders = true;

        // Start each capture with a random number
            $genRandNumberForStartMarker = 1;

        // Blank lines before output
            $blankLinesBetweenOutputs = 1;
            $blankLinesBeforeAllOutputs = 2;
                $allOuputTimeOut = 3;

        // Try to output coloured outputs
            $colourOutput = true;
                $colourDebugHeaders = true;
                    $debugHeaderColor = "\033[32m";
                $colourMethodHeaders = true;
                    $methodHeaderColour = "\033[33m";
                $colourActualOutput = true;
                    $actualOutputColour = "\033[36m";

        // Debug to raw $Capture to StdOut
            $debugToStdOut = 0;

        // State file
            $stateFile = 'state.json';
    // -------------

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

        if ($timeElapsed >= $allOuputTimeOut) {
            //var_dump($timeElapsed);
            for ($i = 1; $i <= $blankLinesBeforeAllOutputs; $i++) {
                $output .= "\n";
            }
        }
    }
    $state->lastStarted = time();



    // Is not first (could be method header or normal dump)
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
    } else if (is_string($capture) && strpos($capture, '**') === 0) {
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
            echo "\nNone tabbed method header";
            if ($colourOutput && $colourMethodHeaders) {
                $output .= "\033[33m";
            }

            $output .= $capture."\n";
            // Trim the ** used to detect method headers
            $output = str_replace('**', '', $output);

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
        } else if ($outputMethod == 'var_export')
        {
            //echo "var_export";
            var_export($capture);
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
        //$output = stripos('media/170/html/dev/bwdebug2/bwdebug2.php:158:', $output);
		$output = strip_tags($output);
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

?>