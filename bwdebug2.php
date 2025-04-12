<?php

/**
 * =============================================================================
 * BwDebug Configuration
 * =============================================================================
 * Centralized configuration for the bwdebug function.
 */
function get_config(): array { // Reverted name
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
        'color_output' => true, // Master switch for colors
        'color_debug_headers' => true,
        'debug_header_color' => "\033[32m", // Green
        'color_method_headers' => true,
        'method_header_color' => "\033[33m", // Yellow
        'color_actual_output' => true,
        'actual_output_color' => "\033[36m", // Cyan
        'color_caller_info' => true, // Control color of caller info
        'caller_info_color' => "\033[1;97;40m", // Bright White text on Black BG
        'color_memory_usage' => true, // Control color of memory usage
        'memory_usage_color' => "\033[90m", // Bright Black (Gray) for memory
        'color_timer' => true, // Control color of timer output
        'timer_color' => "\033[95m", // Bright Magenta for timers
        'color_stack_trace' => true, // Control color of stack trace
        'stack_trace_color' => "\033[90m", // Bright Black (Gray) for stack trace
        'color_label' => true, // Control color of labels
        'label_color' => "\033[1;37m", // Bold White for labels
        'color_reset' => "\033[0m",

        // --- Debugging & Overrides ---
        'debug_to_stdout' => false, // Output raw $capture to standard output (e.g., console)
        'override_xdebug_ini' => true,
        'xdebug_overrides' => [
            'xdebug.var_display_max_depth' => 15,
            'xdebug.var_display_max_children' => 100,
        ],

        'log_memory_usage' => true, // Log current memory usage
        'log_peak_memory_usage' => true, // Log peak memory usage
        'include_stack_trace' => true, // Include stack trace by default (can be overridden per call)
        'stack_trace_depth' => 10, // Max number of frames in stack trace (0 for unlimited)
    ];
}

/**
 * Reads the state from the JSON file. Creates a default state if the file is missing or invalid.
 *
 * @param string $stateFile Path to the state file.
 * @return object The state object.
 */
function read_state(string $stateFile): object { // Reverted name
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
        save_state($stateFile, $defaultState); // Updated call (reverted name)
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
function save_state(string $stateFile, object $state): bool { // Reverted name
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
 * Stores timer start times.
 * @var array<string, float>
 */
global $bwdebug_timers; // Keep prefix for global to reduce collision risk
$bwdebug_timers = [];

/**
 * Starts a timer with a given label.
 *
 * @param string $label Identifier for the timer.
 */
function timer_start(string $label): void { // Reverted name
    global $bwdebug_timers;
    $bwdebug_timers[$label] = microtime(true);
}

/**
 * Ends a timer and logs the elapsed time.
 *
 * @param string $label Identifier for the timer.
 * @param int $fileNum Optional log file number (1 or 2).
 */
function timer_end(string $label, int $fileNum = 1): void { // Reverted name
    global $bwdebug_timers;
    $endTime = microtime(true);
    $startTime = $bwdebug_timers[$label] ?? null;

    if ($startTime === null) {
        bwdebug("Timer '{$label}' was not started.", "[TIMER ERROR]", $fileNum); // Use bwdebug to log the error
        return;
    }

    $elapsed = $endTime - $startTime;
    unset($bwdebug_timers[$label]); // Remove timer after use

    // Format elapsed time
    $elapsedFormatted = number_format($elapsed * 1000, 2) . ' ms'; // Milliseconds

    // Log using bwdebug, passing a specific label for timer output
    bwdebug("Timer '{$label}': {$elapsedFormatted}", "[TIMER]", $fileNum);
}

/**
 * Formats bytes into a human-readable string (KB, MB).
 *
 * @param int $bytes
 * @return string
 */
function format_bytes(int $bytes): string { // Reverted name
    if ($bytes >= 1048576) { // MB
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) { // KB
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Formats a stack trace array into a readable string.
 *
 * @param array $trace The stack trace array from debug_backtrace().
 * @param array $config The configuration array.
 * @return string The formatted stack trace.
 */
function format_stack_trace(array $trace, array $config): string { // Reverted name
    $output = "Stack Trace:\n";
    $resetCode = $config['color_reset'] ?? "\033[0m";
    $colorCode = ($config['color_output'] && $config['color_stack_trace']) ? $config['stack_trace_color'] : '';
    $depth = $config['stack_trace_depth'] ?? 0;
    $frameCount = 0;

    // Skip the first frame (the call to bwdebug itself) and potentially the caller frame
    // Adjust based on where format_stack_trace is called if needed
    if (isset($trace[0]['function']) && $trace[0]['function'] === 'bwdebug') {
        array_shift($trace); // Remove bwdebug frame
    }
    if (isset($trace[0]['function']) && $trace[0]['function'] === 'format_variable_dump') {
        array_shift($trace); // Remove format_variable_dump frame
    }

    foreach ($trace as $index => $frame) {
        if ($depth > 0 && $frameCount >= $depth) {
            $output .= $colorCode . "  ... (trace truncated)\n" . $resetCode;
            break;
        }

        $file = $frame['file'] ?? '[internal function]';
        $line = $frame['line'] ?? '?';
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? '';
        $type = $frame['type'] ?? ''; // '->' or '::'

        $output .= $colorCode . sprintf(
                "  #%d %s:%d\n     %s%s%s()\n",
                $index, // Use original index for reference
                $file,
                $line,
                $class,
                $type,
                $function
            ) . $resetCode;
        $frameCount++;
    }
    return rtrim($output); // Remove trailing newline if any
}

/**
 * Formats the debug header string.
 *
 * @param array $config The configuration array.
 * @return string The formatted header.
 */
function format_debug_header(array $config): string { // Reverted name
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
function format_method_header(string $capture, array $config): string { // Reverted name
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
 * Formats the variable dump output, including caller info, memory, label, and trace.
 *
 * @param mixed $capture The variable to dump.
 * @param ?string $label An optional label for the output.
 * @param array $config The configuration array.
 * @param string|null $callerFile The file where bwdebug was called.
 * @param int|null $callerLine The line where bwdebug was called.
 * @param ?array $trace Optional stack trace array.
 * @return string The formatted variable dump.
 */
function format_variable_dump(mixed $capture, ?string $label, array $config, ?string $callerFile, ?int $callerLine, ?array $trace = null): string { // Reverted name
    $output = "";
    $originalOutputMethod = $config['output_method'];
    $useOutputMethod = $originalOutputMethod;
    $resetCode = $config['color_reset'] ?? "\033[0m";
    $callerInfoLine = "";
    $memoryInfoLine = ""; // NEW: For memory usage
    $labelPrefix = ""; // NEW: For label

    // --- Prepare Caller Info Line ---
    $conditionResult = ($config['show_caller_info'] ?? false) && $callerFile !== null && $callerLine !== null;
    if ($conditionResult) {
        $callerInfoText = $callerFile . ':' . $callerLine . ":";
        if ($config['color_output'] && $config['color_caller_info']) {
            $callerInfoLine = $resetCode . $config['caller_info_color'] . $callerInfoText . $resetCode . "\n"; // Add reset before newline
        } else {
            $callerInfoLine = $callerInfoText . "\n";
        }
    }

    // --- Prepare Memory Info Line ---
    $memUsage = '';
    $peakMemUsage = '';
    if ($config['log_memory_usage'] ?? false) {
        $memUsage = 'Mem: ' . format_bytes(memory_get_usage()); // Updated call (reverted name)
    }
    if ($config['log_peak_memory_usage'] ?? false) {
        $peakMemUsage = 'Peak: ' . format_bytes(memory_get_peak_usage(true)); // Use real usage // Updated call (reverted name)
    }
    if ($memUsage || $peakMemUsage) {
        $memoryInfoText = trim($memUsage . ' ' . $peakMemUsage);
        if ($config['color_output'] && $config['color_memory_usage']) {
            $memoryInfoLine = $resetCode . $config['memory_usage_color'] . $memoryInfoText . $resetCode . "\n"; // Add reset before newline
        } else {
            $memoryInfoLine = $memoryInfoText . "\n";
        }
    }

    // --- Prepare Label Prefix ---
    // Handle both explicit label and the special [TIMER] label
    if ($label === '[TIMER]' || $label === '[TIMER ERROR]') { // Special handling for timer output
        if ($config['color_output'] && $config['color_timer']) {
            // Capture itself contains the timer message
            $labelPrefix = $resetCode . $config['timer_color']; // Color applied later
        } else {
            $labelPrefix = ''; // No special prefix, just the message
        }
        $label = null; // Clear label so it's not prepended again
    } elseif ($label !== null) {
        if ($config['color_output'] && $config['color_label']) {
            $labelPrefix = $resetCode . $config['label_color'] . $label . ': ' . $resetCode;
        } else {
            $labelPrefix = $label . ': ';
        }
    }

    // --- Determine dump method ---
    if ($originalOutputMethod === 'var_dump' && $config['suppress_native_location'] === true) {
        $useOutputMethod = 'print_r';
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

    $dumpOutput = trim($dumpOutput); // Trim captured output

    // --- Build Final Output ---
    $output = $callerInfoLine . $memoryInfoLine; // Start with caller and memory info

    // Apply label prefix and color to the dump output
    $actualOutputColor = ($config['color_output'] && $config['color_actual_output']) ? $config['actual_output_color'] : '';
    if ($labelPrefix && isset($config['timer_color']) && strpos($labelPrefix, $config['timer_color']) !== false) {
        // Apply timer color if labelPrefix contains it
        $output .= $labelPrefix . $dumpOutput . $resetCode;
    } else {
        // Apply label prefix (if any) and standard output color
        $output .= $labelPrefix; // Add label prefix first
        if ($actualOutputColor) {
            $output .= $actualOutputColor . $dumpOutput . $resetCode;
        } else {
            $output .= $dumpOutput;
        }
    }

    // --- Append Stack Trace ---
    if ($trace !== null) {
        $output .= "\n" . format_stack_trace($trace, $config); // Add formatted trace // Updated call (reverted name)
    }

    // Add a single guaranteed newline at the very end
    $output .= "\n";

    return $output;
}

/**
 * Debug function to dump variables to a log file.
 *
 * Examples:
 *   bwdebug("some string");
 *   bwdebug($myVar, "My Variable");             // Output with label
 *   bwdebug($data, "Data Set", 2);              // Output with label to file 2
 *   bwdebug($result, null, 1, false, true);     // Output with stack trace
 *   bwdebug("**File#Class#Method():Line");
 *   timer_start('process');                     // Use timer_start
 *   // ... code ...
 *   timer_end('process');                       // Use timer_end
 *
 * @param mixed $capture The variable or method header string to dump.
 * @param ?string $label Optional label for the output. Defaults to null.
 * @param int $fileNum The file number (1 or 2) to write to. Defaults to 1.
 * @param bool $isFirstEntry Whether this is the first entry (outputs debug header). Defaults to false.
 * @param bool $includeTrace Force include stack trace for this call. Defaults to false.
 */
function bwdebug(mixed $capture, ?string $label = null, int $fileNum = 1, bool $isFirstEntry = false, bool $includeTrace = false): void { // Added $label, $includeTrace
    $config = get_config(); // Updated call (reverted name)
    $stateFile = $config['log_dir'] . '/' . $config['state_file_name'];
    $state = read_state($stateFile); // Updated call (reverted name)

    // --- Get Caller Information & Stack Trace ---
    $callerFile = null;
    $callerLine = null;
    $trace = null; // Initialize trace
    // Get trace regardless, as we need it for caller info OR if requested
    // Provide object context for stack trace if available
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);

    // Determine Caller Frame
    $callerFrame = null;
    // Index 1 is the direct caller of bwdebug
    if (isset($backtrace[1])) {
        $callerFrame = $backtrace[1];
    } elseif (isset($backtrace[0]) && isset($backtrace[0]['file']) && isset($backtrace[0]['line'])) {
        // Fallback for calls from global scope (less common for the direct caller)
        $callerFrame = $backtrace[0];
    }

    // Extract Caller Info
    if ($callerFrame !== null && isset($callerFrame['file']) && isset($callerFrame['line'])) {
        $callerFile = $callerFrame['file'];
        $callerLine = $callerFrame['line'];
    } else {
        error_log("BWDEBUG WARNING: Could not determine caller file/line.");
    }

    // Check if stack trace is needed
    $needsTrace = $includeTrace || ($config['include_stack_trace'] ?? false);
    if ($needsTrace) {
        $trace = $backtrace; // Use the already fetched backtrace
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
        if ($label !== null) echo "Label: " . $label . "\n"; // Include label in stdout
        var_dump($capture);
        if ($needsTrace && $trace) echo format_stack_trace($trace, $config) . "\n"; // Include trace in stdout // Updated call (reverted name)
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
        $formattedContent = format_debug_header($config); // Updated call (reverted name)
        // Optionally dump variable on first entry too? Requires passing more args.
        // $formattedContent .= format_variable_dump($capture, $label, $config, $callerFile, $callerLine, $trace); // Updated call (reverted name)

    } else if (is_string($capture) && strpos($capture, '**') === 0) {
        $formattedContent = format_method_header($capture, $config); // Updated call (reverted name)
    } else {
        // Pass label and trace to the formatting function
        $formattedContent = format_variable_dump($capture, $label, $config, $callerFile, $callerLine, $trace); // Updated call (reverted name)
    }

    $outputString .= $formattedContent;

    // --- Handle Spacing Between Outputs ---
    if ($config['blank_lines_between_outputs'] > 0) {
        $outputString = rtrim($outputString, "\n");
        $outputString .= str_repeat("\n", $config['blank_lines_between_outputs'] + 1);
    } else if (substr($outputString, -1) !== "\n") { // PHP 7.4 compatible
        $outputString .= "\n";
    }

    // --- Write to File ---
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            error_log("BWDEBUG ERROR: Could not create log directory for writing: " . $logDir);
            save_state($stateFile, $state); // Updated call (reverted name)
            return;
        }
    }

    if (file_put_contents($logFilePath, $outputString, FILE_APPEND | LOCK_EX) === false) {
        error_log("BWDEBUG ERROR: Could not write to log file: " . $logFilePath);
    }

    // --- Save State ---
    if (!save_state($stateFile, $state)) { // Updated call (reverted name)
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

    $config = get_config(); // Updated call (reverted name)

    // Check for specific test flags
    if (in_array('--test-colors', $argv)) {

        /**
         * Outputs a list of the configured ANSI color names, each displayed in its own color.
         * Requires a terminal that supports ANSI escape codes.
         */
        function test_colors(): void {
            $config = get_config();
            $resetCode = $config['color_reset'] ?? "\033[0m";

            echo $resetCode."--- Testing BWDebug Colors ---\n";

            $colorMappings = [
                'Debug Header' => 'debug_header_color',
                'Method Header' => 'method_header_color',
                'Actual Output' => 'actual_output_color',
                'Caller Info' => 'caller_info_color',
                'Memory Usage' => 'memory_usage_color',
                'Timer' => 'timer_color',
                'Stack Trace' => 'stack_trace_color',
                'Label' => 'label_color',
            ];

            foreach ($colorMappings as $name => $configKey) {
                if (isset($config[$configKey])) {
                    $colorCode = $config[$configKey];
                    echo $name . ": " . $colorCode . "Sample Text (" . addslashes($colorCode) . ")".$resetCode."\n";
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

        // --- Test Function
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

            // label parameter
            bwdebug($lines, 'Generated HTML Lines');
            return $lines;
        }

        echo "Running general bwdebug examples...\n";

        // Example 1: Simple string with header
        bwdebug("Starting debug session...", null, 1, true); // Label is null

        // Example 2: Simple array (no label)
        bwdebug($birds);

        // Example 3: Labelled array to file 2
        bwdebug($fruit, "Current Fruit", 2); // Use label param

        // Example 4: Method header for test function & call with trace
        timer_start('html_generation'); // Start timer
        $randomHtmlArray = genRandHtml(1);
        bwdebug($randomHtmlArray, "HTML Array Result", 1, false, true); // Force trace
        timer_end('html_generation'); // End timer

        // Example 5: Another variable dump to file 1 with label
        bwdebug($cars, "Car List");

        // Example 6: Integer
        bwdebug(12345);

        // Example 7: Output to file 2 with header
        bwdebug("This goes to file 2, first entry.", null, 2, true);
        bwdebug($cars, "Cars again", 2);

        echo "General bwdebug examples finished. Check log files.\n";
    }

} // End CLI check

?>