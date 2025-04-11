<?php

/**
 * =============================================================================
 * BwDebug Configuration
 * =============================================================================
 * Centralized configuration for the bwdebug function.
 */
function bwdebug_get_config(): array {
    return [
        // --- File Paths ---
        'log_dir' => __DIR__ . '/logs', // Base directory for logs
        'default_output_file_name' => 'output.log',
        'second_output_file_name' => 'output2.log',
        'state_file_name' => 'state.json',

        // --- dev ---
        'run_tests' => true,

        // --- Output Formatting ---
        'output_method' => 'var_dump', // 'var_dump', 'print_r', 'var_export'
        'strip_tags_from_var_dump' => true, // Only applies if output_method is 'var_dump'
        'tab_out_method_headers' => true,
        'gen_rand_number_for_start_marker' => true,

        // --- Spacing & Timing ---
        'blank_lines_between_outputs' => 1, // lines or 0 to disable
        'blank_lines_before_all_outputs' => 5, // lines or 0 to disable
        'all_output_timeout_seconds' => 3, // Minimum seconds between "before_all" spacing

        // --- Colorization (ANSI Escape Codes) ---
        'color_output' => true, // Master switch for colors
        'color_debug_headers' => true,
        'debug_header_color' => "\033[32m", // Green
        'color_method_headers' => true,
        'method_header_color' => "\033[33m", // Yellow
        'color_actual_output' => true,
        'actual_output_color' => "\033[36m", // Cyan
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
function bwdebug_read_state(string $stateFile): object {
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
        // Attempt to save default, but return default state regardless
        bwdebug_save_state($stateFile, $defaultState);
        return $defaultState;
    }

    $stateJson = file_get_contents($stateFile);
    if ($stateJson === false) {
        error_log("BWDEBUG ERROR: Could not read from state file: " . $stateFile);
        return $defaultState;
    }

    $state = json_decode($stateJson);
    if (json_last_error() !== JSON_ERROR_NONE || empty($state)) {
        error_log("BWDEBUG ERROR: Invalid JSON or empty state file: " . $stateFile . " - JSON Error: " . json_last_error_msg());
        // Optionally, try to save a default state here if the file is corrupt
        // bwdebug_save_state($stateFile, $defaultState);
        return $defaultState;
    }

    // Ensure essential properties exist
    if (!isset($state->lastStarted)) {
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
function bwdebug_save_state(string $stateFile, object $state): bool {
    $stateJson = json_encode($state, JSON_PRETTY_PRINT); // Add pretty print for readability
    if ($stateJson === false) {
        error_log("BWDEBUG ERROR: Could not encode state to JSON. Error: " . json_last_error_msg());
        return false;
    }

    if (file_put_contents($stateFile, $stateJson) === false) {
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
function bwdebug_format_debug_header(array $config): string {
    $output = "";
    $color = $config['color_output'] && $config['color_debug_headers'];

    if ($color) {
        $output .= $config['debug_header_color'];
    }

    $output .= "-";
    if ($config['gen_rand_number_for_start_marker']) {
        $output .= str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    }
    $output .= "-----" . date('H:i:s') . "------------\n";

    if ($color) {
        $output .= $config['color_reset'];
    }
    return $output;
}

/**
 * Formats a method header string.
 *
 * @param string $capture The raw method header string (starting with **).
 * @param array $config The configuration array.
 * @return string The formatted method header.
 */
function bwdebug_format_method_header(string $capture, array $config): string {
    $output = "";
    $color = $config['color_output'] && $config['color_method_headers'];
    $cleanedCapture = str_replace('**', '', $capture); // Remove marker

    if ($color) {
        $output .= $config['method_header_color'];
    }

    if ($config['tab_out_method_headers']) {
        $parts = explode("#", $cleanedCapture);
        $output .= implode("\t", $parts);
        $output = rtrim($output, "\t"); // Remove trailing tab if any
    } else {
        $output .= str_replace('#', ' ', $cleanedCapture);
    }

    if ($color) {
        $output .= $config['color_reset'];
    }

    return $output . "\n";
}

/**
 * Formats the variable dump output.
 *
 * @param mixed $capture The variable to dump.
 * @param array $config The configuration array.
 * @return string The formatted variable dump.
 */
function bwdebug_format_variable_dump(mixed $capture, array $config): string {
    ob_start();
    switch ($config['output_method']) {
        case 'var_dump':
            var_dump($capture);
            break;
        case 'var_export':
            var_export($capture); // Note: var_export might be better sometimes
            break;
        case 'print_r':
        default:
            print_r($capture);
            break;
    }
    $output = ob_get_clean();

    // Apply post-processing specific to output methods
    if ($config['output_method'] === 'var_dump' && $config['strip_tags_from_var_dump']) {
        $output = strip_tags($output);
        // Decode common HTML entities that might appear
        $output = html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Apply color if configured
    if ($config['color_output'] && $config['color_actual_output']) {
        $output = $config['actual_output_color'] . $output . $config['color_reset'];
    }

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
    $config = bwdebug_get_config();
    $stateFile = $config['log_dir'] . '/' . $config['state_file_name'];
    $state = bwdebug_read_state($stateFile);

    // --- Apply Xdebug Overrides ---
    if ($config['override_xdebug_ini']) {
        foreach ($config['xdebug_overrides'] as $key => $value) {
            ini_set($key, (string)$value); // ini_set expects string value
        }
    }

    // --- Determine Log File ---
    $logFileName = ($fileNum === 2)
        ? $config['second_output_file_name']
        : $config['default_output_file_name'];
    $logFilePath = $config['log_dir'] . '/' . $logFileName;

    // --- Debug to Standard Output ---
    if ($config['debug_to_stdout']) {
        echo "--- BWDEBUG STDOUT ---\n";
        var_dump($capture);
        echo "----------------------\n";
    }

    // --- Prepare Output String ---
    $outputString = "";

    // --- Handle Spacing Before All Outputs ---
    $currentTime = time();
    $timeElapsed = $currentTime - $state->lastStarted;
    if ($config['blank_lines_before_all_outputs'] > 0 && $timeElapsed >= $config['all_output_timeout_seconds']) {
        // $outputString .= "\n: ".$timeElapsed; // Optional: Show time elapsed
        $outputString .= str_repeat("\n", $config['blank_lines_before_all_outputs']);
    }
    // Update lastStarted *after* checking the time elapsed
    $state->lastStarted = $currentTime;


    // --- Format Main Content ---
    $formattedContent = "";
    if ($isFirstEntry) {
        $formattedContent = bwdebug_format_debug_header($config);
        // When it's the first entry, we often don't want the actual $capture content,
        // just the header. If you *do* want both, you'd append the variable dump below.
        // For now, let's assume $isFirstEntry implies *only* the header.
        // If you need both, remove the 'else' below and adjust logic.

    } else if (is_string($capture) && strpos($capture, '**') === 0) {
        // It's a method header
        $formattedContent = bwdebug_format_method_header($capture, $config);
    } else {
        // It's a variable dump
        $formattedContent = bwdebug_format_variable_dump($capture, $config);
    }

    $outputString .= $formattedContent;


    // --- Handle Spacing Between Outputs ---
    // Add blank lines *after* the content, before writing
    if ($config['blank_lines_between_outputs'] > 0) {
        $outputString .= str_repeat("\n", $config['blank_lines_between_outputs']);
    }

    // --- Write to File ---
    // Ensure the directory exists (might be redundant if readState already did, but safe)
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            error_log("BWDEBUG ERROR: Could not create log directory for writing: " . $logDir);
            // Optionally save state even if write fails? Depends on desired behavior.
            bwdebug_save_state($stateFile, $state);
            return; // Stop execution for this call if dir fails
        }
    }

    if (file_put_contents($logFilePath, $outputString, FILE_APPEND | LOCK_EX) === false) { // Added LOCK_EX for safety
        error_log("BWDEBUG ERROR: Could not write to log file: " . $logFilePath);
    }

    // --- Save State ---
    bwdebug_save_state($stateFile, $state);
}


/**
 * =============================================================================
 * Test Area (Keep separate from the library functions)
 * =============================================================================
 */

// --- Test Data ---
$birds = ['blue', 'tit', 'pigeon', 'nested' => ['robin', 'sparrow']];
$fruit = ['apple', 'banana', 'pear'];
$cars = ['ford', 'peugeot', 'vauxhall'];

// --- Test Function ---
function genRandHtml(int $num_lines): array { // Return array as intended by commented out code
    // Use bwdebug to mark entry into this test function
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

    // Use bwdebug to show the result before returning
    bwdebug(['Generated HTML Lines', $lines]);
    return $lines;
}


// --- Example Calls ---
$config = bwdebug_get_config();
if ($config['run_tests'] == 1) {
    echo "Running bwdebug examples...\n";

    // Example 1: Simple string with header
    bwdebug("Starting debug session...", 1, true);

    // Example 2: Simple array
    bwdebug($birds);

    // Example 3: Labelled array to file 2
    bwdebug(["Current Fruit", $fruit], 2);

    // Example 4: Method header for test function
    // (genRandHtml calls bwdebug internally for its header)
    $randomHtmlArray = genRandHtml(2);

    // Example 5: Another variable dump to file 1
    bwdebug($cars);

    // Example 6: Integer
    bwdebug(12345);

    // Example 7: Output to file 2 with header
    bwdebug("This goes to file 2, first entry.", 2, true);
    bwdebug($cars, 2);


    echo "bwdebug examples finished. Check log files.\n";
}

?>