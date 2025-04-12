<?php

/**
 * =============================================================================
 * BwDebug Configuration
 * =============================================================================
 * Centralized configuration for the bwdebug function.
 */
function get_config(): array { // Renamed
    return [
        // --- File Paths ---
        'log_dir' => __DIR__ . '/logs', // Base directory for logs
        'default_output_file_name' => 'output.log',
        'second_output_file_name' => 'output2.log',
        'state_file_name' => 'state.json',

        // --- dev ---
        'run_tests' => false,

        // --- Output Formatting ---
        'output_method' => 'var_dump', // 'var_dump', 'print_r', 'var_export'
        'strip_tags_from_var_dump' => true, // Only applies if output_method is 'var_dump'
        'suppress_native_location' => true, // Suppress the (bwdebug:LINE),  location info added by var_dump/Xdebug
        'tab_out_method_headers' => true,
        'gen_rand_number_for_start_marker' => true,
        'show_caller_info' => true, // Control display of caller file/line

        // --- Spacing & Timing ---
        'blank_lines_between_outputs' => 1, // lines or 0 to disable
        'blank_lines_before_all_outputs' => 5, // lines or 0 to disable
        'all_output_timeout_seconds' => 3, // Minimum seconds between "before_all" spacing

        // --- Colorization (ANSI Escape Codes) ---
        'color_output' => true, // Master switch for colors - RE-ENABLED
        'color_debug_headers' => true,
        'debug_header_color' => "\033[32m", // Green
        'color_method_headers' => true,
        'method_header_color' => "\033[33m", // Yellow
        'color_actual_output' => true,
        'actual_output_color' => "\033[36m", // Cyan
        'color_caller_info' => true, // Control color of caller info
        'caller_info_color' => "\033[1;97;40m", // Bright White text on Black BG
        'color_reset' => "\033[0m",

        // --- Debugging & Overrides ---
        'debug_to_stdout' => false, // Output raw $capture to standard output (e.g., console)
        'override_xdebug_ini' => true,
        'xdebug_overrides' => [
            'xdebug.var_display_max_depth' => 15,
            'xdebug.var_display_max_children' => 100,
            // Add other xdebug settings if needed
        ],
    ];
}

/**
 * =============================================================================
 * State Management Functions
 * =============================================================================
 */

/**
 * Reads the state from the JSON file. Creates a default state if the file is missing or invalid.
 *
 * @param string $stateFile Path to the state file.
 * @return object The state object.
 */
function read_state(string $stateFile): object { // Renamed
    $defaultState = (object)['lastStarted' => 0];

    // Ensure the directory exists
    $logDir = dirname($stateFile);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            error_log("BWDEBUG ERROR: Could not create log directory: " . $logDir);
            return $defaultState;
        }
    }

    if (!file_exists($stateFile)) {
        error_log("BWDEBUG INFO: State file missing, creating default: " . $stateFile);
        save_state($stateFile, $defaultState); // Updated call
        return $defaultState;
    }

    $stateJson = file_get_contents($stateFile);
    if ($stateJson === false) {
        error_log("BWDEBUG ERROR: Could not read from state file: " . $stateFile);
        return $defaultState;
    }

    $state = json_decode($stateJson);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($state)) {
        error_log("BWDEBUG ERROR: Invalid JSON or not an object in state file: " . $stateFile . " - JSON Error: " . json_last_error_msg());
        return $defaultState;
    }

    if (!property_exists($state, 'lastStarted')) {
        $state->lastStarted = 0;
    }

    return $state;
}

/**
 * Saves the state object to the JSON file.
 *
 * @param string $stateFile Path to the state file.
 * @param object $state The state object to save.
 * @return bool True on success, false on failure.
 */
function save_state(string $stateFile, object $state): bool { // Renamed
    $stateJson = json_encode($state, JSON_PRETTY_PRINT);
    if ($stateJson === false) {
        error_log("BWDEBUG ERROR: Could not encode state to JSON. Error: " . json_last_error_msg());
        return false;
    }

    if (file_put_contents($stateFile, $stateJson, LOCK_EX) === false) {
        error_log("BWDEBUG ERROR: Could not write state file: " . $stateFile);
        return false;
    }
    return true;
}

/**
 * =============================================================================
 * Formatting Functions
 * =============================================================================
 */

/**
 * Formats the debug header string.
 *
 * @param array $config The configuration array.
 * @return string The formatted header.
 */
function format_debug_header(array $config): string { // Renamed
    $output = "";
    $color = $config['color_output'] && $config['color_debug_headers'];
    $resetCode = $config['color_reset'] ?? "\033[0m";

    if ($color) {
        $output .= $config['debug_header_color'];
    }

    $output .= "-";
    if ($config['gen_rand_number_for_start_marker']) {
        $output .= str_pad((string)rand(0, 99), 2, '0', STR_PAD_LEFT);
    }
    $output .= "-----" . date('H:i:s') . "------------";

    if ($color) {
        $output .= $resetCode; // Add reset ONLY if color was added
    }
    return $output . "\n"; // Add newline at the very end
}

/**
 * Formats a method header string.
 *
 * @param string $capture The raw method header string (starting with **).
 * @param array $config The configuration array.
 * @return string The formatted method header.
 */
function format_method_header(string $capture, array $config): string { // Renamed
    $output = "";
    $color = $config['color_output'] && $config['color_method_headers'];
    $resetCode = $config['color_reset'] ?? "\033[0m";
    $cleanedCapture = str_replace('**', '', $capture);

    if ($color) {
        $output .= $config['method_header_color'];
    }

    if ($config['tab_out_method_headers']) {
        $parts = explode("#", $cleanedCapture);
        $output .= implode("\t", $parts);
        $output = rtrim($output, "\t");
    } else {
        $output .= str_replace('#', ' ', $cleanedCapture);
    }

    if ($color) {
        $output .= $resetCode; // Add reset ONLY if color was added
    }

    return $output . "\n"; // Add newline at the very end
}

/**
 * Formats the variable dump output, including caller info.
 *
 * @param mixed $capture The variable to dump.
 * @param array $config The configuration array.
 * @param string|null $callerFile The file where bwdebug was called.
 * @param int|null $callerLine The line where bwdebug was called.
 * @return string The formatted variable dump.
 */
function format_variable_dump(mixed $capture, array $config, ?string $callerFile, ?int $callerLine): string { // Renamed
    $output = "";
    $originalOutputMethod = $config['output_method'];
    $useOutputMethod = $originalOutputMethod;
    $resetCode = $config['color_reset'] ?? "\033[0m";
    $callerInfoLine = ""; // Store caller info separately

    // --- Prepare Caller Info Line ---
    $conditionResult = ($config['show_caller_info'] ?? false) && $callerFile !== null && $callerLine !== null;
    if ($conditionResult) {
        $callerInfoText = $callerFile . ':' . $callerLine . ":";
        if ($config['color_output'] && $config['color_caller_info']) {
            // Build the line: Reset + Color + Text + NEWLINE + Reset
            $callerInfoLine = $resetCode
                . $config['caller_info_color'] . $callerInfoText . "\n" . $resetCode;
        } else {
            // Build the line: Just Text + NEWLINE (NO ANSI codes if color_output is false)
            $callerInfoLine = $callerInfoText . "\n";
        }
    } else {
        $callerInfoLine = ""; // Ensure it's empty if condition fails
    }

    // --- Determine dump method ---
    if ($originalOutputMethod === 'var_dump' && $config['suppress_native_location'] === true) {
        $useOutputMethod = 'print_r'; // Use print_r to avoid var_dump's location info
    }

    // --- Dump Variable ---
    ob_start();
    switch ($useOutputMethod) {
        case 'var_export': var_export($capture); break;
        case 'print_r':    print_r($capture);    break;
        case 'var_dump':
        default:           var_dump($capture);   break;
    }
    $dumpOutput = ob_get_clean();

    // --- Post-processing ---
    if ($originalOutputMethod === 'var_dump' && $config['strip_tags_from_var_dump']) {
        $dumpOutput = strip_tags($dumpOutput);
        $dumpOutput = html_entity_decode($dumpOutput, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // *** Explicitly trim the dump output to remove leading/trailing whitespace/newlines ***
    $dumpOutput = trim($dumpOutput);

    // --- Build Final Output ---
    $output = $callerInfoLine; // Start with the caller info line (includes \n)

    // Append dump output (colored or not)
    if ($config['color_output'] && $config['color_actual_output']) {
        // Start with color, add trimmed dump, end with reset
        $output .= $config['actual_output_color'] . $dumpOutput . $resetCode;
    } else {
        // Just append trimmed dump output
        $output .= $dumpOutput;
    }

    // *** Add a single guaranteed newline at the very end ***
    $output .= "\n";

    return $output;
}

/**
 * =============================================================================
 * Core Debug Function
 * =============================================================================
 */

/**
 * Debug function to dump variables to a log file.
 *
 * Examples:
 *   bwdebug("some string");                     // Output "some string" to default file
 *   bwdebug(["fruit", $fruit]);                // Output labelled array to default file
 *   bwdebug("a string", 2, true);              // Output "a string" to file 2 with header
 *   bwdebug(["fruit", $fruit], 2);             // Labelled array to file 2
 *   bwdebug("a string", 1, true);              // Output "a string" to File 1 with header
 *   bwdebug(1);                                // Output int(1) to default file
 *   bwdebug("**File#Class#Method():Line");     // Output a method header
 *
 * @param mixed $capture The variable or method header string to dump.
 * @param int $fileNum The file number (1 or 2) to write to. Defaults to 1.
 * @param bool $isFirstEntry Whether this is the first entry (outputs debug header). Defaults to false.
 */
function bwdebug(mixed $capture, int $fileNum = 1, bool $isFirstEntry = false): void {
    $config = get_config(); // Updated call
    $stateFile = $config['log_dir'] . '/' . $config['state_file_name'];
    $state = read_state($stateFile); // Updated call

    // --- Get Caller Information ---
    $callerFile = null;
    $callerLine = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    // *** ADJUSTED LOGIC: Check frame [1] first, then fall back to [0] ***
    $callerFrame = null;
    if (isset($backtrace[1])) {
        // Called from within a function/method, use frame 1
        $callerFrame = $backtrace[1];
    } elseif (isset($backtrace[0])) {
        // Potentially called from global scope, use frame 0
        // Check if 'file' and 'line' exist in frame 0
        if (isset($backtrace[0]['file']) && isset($backtrace[0]['line'])) {
            $callerFrame = $backtrace[0];
        }
    }

    // Now extract info if we found a valid frame
    if ($callerFrame !== null && isset($callerFrame['file']) && isset($callerFrame['line'])) {
        $callerFile = $callerFrame['file'];
        $callerLine = $callerFrame['line'];
    } else {
        // Log warning if caller info couldn't be determined
        error_log("BWDEBUG WARNING: Could not determine caller file/line.");
    }

    // --- Apply Xdebug Overrides ---
    if ($config['override_xdebug_ini']) {
        foreach ($config['xdebug_overrides'] as $key => $value) {
            ini_set($key, (string)$value);
        }
    }

    // --- Determine Log File ---
    $logFileName = ($fileNum === 2)
        ? $config['second_output_file_name']
        : $config['default_output_file_name'];
    $logFilePath = $config['log_dir'] . '/' . $logFileName;

    // --- Debug to Standard Output ---
    if ($config['debug_to_stdout']) {
        $resetCode = $config['color_reset'] ?? "\033[0m";
        echo $resetCode . "--- BWDEBUG STDOUT (" . ($callerFile ?? 'UnknownFile') . ":" . ($callerLine ?? 'UnknownLine') . ") ---\n";
        var_dump($capture);
        echo "----------------------\n" . $resetCode;
    }

    // --- Prepare Output String ---
    $outputString = "";

    // --- Handle Spacing Before All Outputs ---
    $currentTime = time();
    $timeElapsed = $currentTime - ($state->lastStarted ?? 0);
    if ($config['blank_lines_before_all_outputs'] > 0 && $timeElapsed >= $config['all_output_timeout_seconds']) {
        $outputString .= str_repeat("\n", $config['blank_lines_before_all_outputs']);
    }
    $state->lastStarted = $currentTime;


    // --- Format Main Content ---
    $formattedContent = "";
    if ($isFirstEntry) {
        $formattedContent = format_debug_header($config); // Updated call
        // If you want the first entry to ALSO dump the variable, uncomment the next line:
        // $formattedContent .= format_variable_dump($capture, $config, $callerFile, $callerLine); // Updated call

    } else if (is_string($capture) && strpos($capture, '**') === 0) {
        $formattedContent = format_method_header($capture, $config);
    } else {
        $formattedContent = format_variable_dump($capture, $config, $callerFile, $callerLine);
    }

    $outputString .= $formattedContent;

    // --- Handle Spacing Between Outputs ---
    if ($config['blank_lines_between_outputs'] > 0) {
        $outputString = rtrim($outputString, "\n");
        $outputString .= str_repeat("\n", $config['blank_lines_between_outputs'] + 1);
    } else if (substr($outputString, -1) !== "\n") { // Replaced str_ends_with for PHP 7.4 compatibility
        $outputString .= "\n";
    }

    // --- Write to File ---
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            error_log("BWDEBUG ERROR: Could not create log directory for writing: " . $logDir);
            save_state($stateFile, $state); // Updated call
            return;
        }
    }

    if (file_put_contents($logFilePath, $outputString, FILE_APPEND | LOCK_EX) === false) {
        error_log("BWDEBUG ERROR: Could not write to log file: " . $logFilePath);
    }

    // --- Save State ---
    if (!save_state($stateFile, $state)) {
        error_log("BWDEBUG WARNING: Failed to save state file: " . $stateFile);
    }
}

/**
 * ================================
 * Command-Line Argument Handling
 * ================================
 */
if (php_sapi_name() === 'cli') {
    global $argv;

    $config = get_config(); // Updated call

    // Check for specific test flags
    if (in_array('--test-colors', $argv)) {

        /**
         * Outputs a list of the configured ANSI color names, each displayed in its own color.
         * Requires a terminal that supports ANSI escape codes.
         */
        function test_colors(): void { // Renamed & Moved
            $config = get_config(); // Updated call
            $resetCode = $config['color_reset'] ?? "\033[0m";

            echo $resetCode."--- Testing BWDebug Colors ---\n";

            $colorMappings = [
                'Debug Header' => 'debug_header_color',
                'Method Header' => 'method_header_color',
                'Actual Output' => 'actual_output_color',
                'Caller Info' => 'caller_info_color',
            ];

            foreach ($colorMappings as $name => $configKey) {
                if (isset($config[$configKey])) {
                    $colorCode = $config[$configKey];
                    echo $name . ": " . $colorCode . "Sample Text (" . addslashes($colorCode) . ")\n". $resetCode;
                } else {
                    echo $name . ": Not configured\n";
                }
            }
            echo "--- Color Test Finished ---\n";
        }

        echo "Running color test...\n";
        test_colors();
        exit;
    }

    // --- Fallback to General Test Execution ---
    if ($config['run_tests'] == 1) {

        // --- Test Data --- (Moved inside conditional block)
        $birds = ['blue', 'tit', 'pigeon', 'nested' => ['robin', 'sparrow']];
        $fruit = ['apple', 'banana', 'pear'];
        $cars = ['ford', 'peugeot', 'vauxhall'];

        // --- Test Function --- (Moved inside conditional block)
        function genRandHtml(int $num_lines): array {
            bwdebug("**" . __DIR__ . "#" . basename(__FILE__) . "#" . __FUNCTION__ . "():" . __LINE__);

            $lines = [];
            $elements = [
                "<h1>This is a random heading</h1>",
                "<p>This is a random paragraph</p>",
                "<ul><li>Random list item 1</li><li>Random list item 2</li></ul>"
            ];

            for ($i = 0; $i < $num_lines; $i++) {
                $lines[] = $elements[array_rand($elements)];
            }

            bwdebug(['Generated HTML Lines', $lines]);
            return $lines;
        }

        echo "Running general bwdebug examples...\n";

        // Example 1: Simple string with header
        bwdebug("Starting debug session...", 1, true);

        // Example 2: Simple array
        bwdebug($birds);

        // Example 3: Labelled array to file 2
        bwdebug(["Current Fruit", $fruit], 2);

        // Example 4: Method header for test function
        $randomHtmlArray = genRandHtml(2);

        // Example 5: Another variable dump to file 1
        bwdebug($cars);

        // Example 6: Integer
        bwdebug(12345);

        // Example 7: Output to file 2 with header
        bwdebug("This goes to file 2, first entry.", 2, true);
        bwdebug($cars, 2);

        echo "General bwdebug examples finished. Check log files.\n";
    }
}

?>