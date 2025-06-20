<?php

// config
function get_config(): array {
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
        'show_caller_info' => true, // Control display of caller file/line
        'print_header_once_per_run' => true, // Control whether to print the debug header only once per "run"
        'suppress_native_location' => true, // Suppress the (bwdebug:LINE),  location info added by var_dump/Xdebug
        'strip_tags_from_var_dump' => false, // Only applies if output_method is 'var_dump'
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

        'log_memory_usage' => false, // Log current memory usage
        'log_peak_memory_usage' => false, // Log peak memory usage
        'stack_trace_depth' => 10, // Max number of frames in stack trace (0 for unlimited)
    ];
}

/**
 * Reads the state from the JSON file. Creates a default state if the file is missing or invalid.
 *
 * @param string $stateFile Path to the state file.
 * @return object The state object.
 */
function read_state(string $stateFile): object {
    // Initialize default state with new property for header tracking
    $defaultState = (object)[
        'lastStarted' => 0,
        'headerPrintedForCurrentRun' => false // New property to track header printing
    ];

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
        save_state($stateFile, $defaultState);
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

    // Ensure all expected properties exist, providing defaults if missing (for backward compatibility)
    if (!property_exists($state, 'lastStarted')) {
        $state->lastStarted = 0;
    }
    if (!property_exists($state, 'headerPrintedForCurrentRun')) {
        $state->headerPrintedForCurrentRun = false; // Initialize if upgrading from older state file
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
function save_state(string $stateFile, object $state): bool {
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
global $bwdebug_timers; // Global to reduce collision risk
$bwdebug_timers = [];

/**
 * Starts a timer with a given label.
 *
 * @param string $label Identifier for the timer.
 */
function timer_start(string $label): void {
    global $bwdebug_timers;
    $bwdebug_timers[$label] = microtime(true);
}

/**
 * Ends a timer and logs the elapsed time.
 *
 * @param string $label Identifier for the timer.
 * @param int $fileNum Optional log file number (1 or 2).
 */
function timer_end(string $label, int $fileNum = 1): void {
    global $bwdebug_timers;
    $endTime = microtime(true);
    $startTime = $bwdebug_timers[$label] ?? null;

    if ($startTime === null) {
        bwdebug("Timer '{$label}' was not started.", "[TIMER ERROR]", $fileNum);
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
function format_bytes(int $bytes): string {
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
function format_stack_trace(array $trace, array $config): string {
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
function format_debug_header(array $config): string {
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
function format_method_header(string $capture, array $config): string {
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
function format_variable_dump($capture, ?string $label, array $config, ?string $callerFile, ?int $callerLine, ?array $trace = null): string {
    $output = "";
    $originalOutputMethod = $config['output_method'];
    $useOutputMethod = $originalOutputMethod;
    $resetCode = $config['color_reset'] ?? "\033[0m";
    $callerInfoLine = "";
    $memoryInfoLine = "";
    $labelPrefix = "";

    // Prepare Caller Info Line
    $conditionResult = ($config['show_caller_info'] ?? false) && $callerFile !== null && $callerLine !== null;
    if ($conditionResult) {
        $callerInfoText = $callerFile . ':' . $callerLine . ":";
        if ($config['color_output'] && $config['color_caller_info']) {
            $callerInfoLine = $resetCode . $config['caller_info_color'] . $callerInfoText . $resetCode . "\n"; // Add reset before newline
        } else {
            $callerInfoLine = $callerInfoText . "\n";
        }
    }

    // Prepare Memory Info Line
    $memUsage = '';
    $peakMemUsage = '';
    if ($config['log_memory_usage'] ?? false) {
        $memUsage = 'Mem: ' . format_bytes(memory_get_usage());
    }
    if ($config['log_peak_memory_usage'] ?? false) {
        $peakMemUsage = 'Peak: ' . format_bytes(memory_get_peak_usage(true)); // Use real usage
    }
    if ($memUsage || $peakMemUsage) {
        $memoryInfoText = trim($memUsage . ' ' . $peakMemUsage);
        if ($config['color_output'] && $config['color_memory_usage']) {
            $memoryInfoLine = $resetCode . $config['memory_usage_color'] . $memoryInfoText . $resetCode . "\n"; // Add reset before newline
        } else {
            $memoryInfoLine = $memoryInfoText . "\n";
        }
    }

    // Prepare Label Prefix
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

    // Determine dump method
    if ($originalOutputMethod === 'var_dump' && $config['suppress_native_location'] === true) {
        $useOutputMethod = 'print_r';
    }

    // Dump Variable
    ob_start();
    switch ($useOutputMethod) {
        case 'var_export': var_export($capture); break;
        case 'print_r':    print_r($capture);    break;
        case 'var_dump':
        default:           var_dump($capture);   break;
    }
    $dumpOutput = ob_get_clean();

    // Post-processing
    if ($originalOutputMethod === 'var_dump' && $config['strip_tags_from_var_dump']) {
        $dumpOutput = strip_tags($dumpOutput);
        $dumpOutput = html_entity_decode($dumpOutput, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $dumpOutput = trim($dumpOutput); // Trim captured output

    // Build Final Output
    $output = $callerInfoLine . $memoryInfoLine; // Start with caller and memory info

    // Apply label prefix and color to the dump output
    // (This part will be handled by the bwdebug function itself now for actual output coloring)
    if ($labelPrefix) {
        $output .= $labelPrefix;
    }

    // Apply main actual output color if enabled
    if ($config['color_output'] && $config['color_actual_output']) {
        $output .= $config['actual_output_color'];
    }

    $output .= $dumpOutput . $resetCode . "\n"; // Add reset code after dump and a newline

    // Append stack trace if requested
    //if ($trace !== null && ($config['include_stack_trace'] ?? false)) {
    if ($trace !== null) {
        $output .= format_stack_trace($trace, $config) . "\n";
    }

    return $output;
}

/**
 * Debug function to dump variables to a log file.
 *
 * Examples:
 * bwdebug("some string");
 * bwdebug($myVar, "My Variable");             // Output with label to default file (1)
 * bwdebug($data, "Data Set", 2);              // Output with label to file 2
 * bwdebug($result, 'my stack trace', 1, true);            // Output with stack trace (last arg is includeTrace)
 * bwdebug("**File#Class#Method():Line");
 * timer_start('process');
 * // ... code ...
 * timer_end('process');
 *
 * @param mixed $capture The variable or message to debug.
 * @param string|null $label An optional label for the output.
 * @param int $fileNum Optional log file number (1 or 2).
 * @param bool $includeTrace Whether to include a stack trace in the output.
 */
function bwdebug($capture, ?string $label = null, int $fileNum = 1, bool $includeTrace = false): void {
    // Determine the actual caller's file and line number
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    $callerFile = '[unknown file]';
    $callerLine = '[unknown line]';

    // Iterate through the stack to find the first frame that is NOT from bwdebug2.php.
    // This robustly finds the line in the user's script that called bwdebug.
    $foundCaller = false;
    foreach ($trace as $i => $frame) {
        // Skip frames that are part of the bwdebug system (heuristically by checking the file name).
        // This assumes all bwdebug internal functions are within 'bwdebug2.php'.
        if (isset($frame['file']) && str_contains($frame['file'], 'bwdebug2.php')) {
            continue;
        }

        // If we reach here, this frame is likely the actual caller from the user's script.
        if (isset($frame['file']) && isset($frame['line'])) {
            $callerFile = $frame['file'];
            $callerLine = $frame['line'];
            $foundCaller = true;
            break; // Found the caller, stop searching.
        }
    }

    // Fallback if no external caller was found (e.g., bwdebug called directly within bwdebug2.php
    // or in a very shallow script without a distinct caller frame).
    if (!$foundCaller && isset($trace[0])) {
        $callerFile = $trace[0]['file'] ?? '[unknown file]';
        $callerLine = $trace[0]['line'] ?? '[unknown line]';
    }

    $config = get_config(); // Ensure config is loaded

    // Override xdebug settings if enabled
    if ($config['override_xdebug_ini'] ?? false) {
        foreach ($config['xdebug_overrides'] as $key => $value) {
            ini_set($key, (string)$value);
        }
    }

    // Determine output file
    $outputFile = $config['log_dir'] . '/' . $config['default_output_file_name'];
    if ($fileNum === 2) {
        $outputFile = $config['log_dir'] . '/' . $config['second_output_file_name'];
    }

    // Ensure log directory exists
    $logDir = dirname($outputFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    // Load state for tracking lastStarted and headerPrintedForCurrentRun
    $stateFile = $config['log_dir'] . '/' . $config['state_file_name'];
    $state = read_state($stateFile);
    $currentTime = microtime(true);

    $isNewRun = false;
    // Determine if enough time has passed to consider it a "new run"
    if (($config['all_output_timeout_seconds'] ?? 0) > 0 && ($currentTime - ($state->lastStarted ?? 0)) > ($config['all_output_timeout_seconds'] ?? 0)) {
        $isNewRun = true;
    }

    $finalOutput = "";

    // If it's a new run (based on timeout), reset the headerPrintedForCurrentRun flag
    if ($isNewRun) {
        $state->headerPrintedForCurrentRun = false; // Reset the flag for a new run
        // Add blank lines before all outputs if configured for new runs
        if (($config['blank_lines_before_all_outputs'] ?? 0) > 0) {
            for ($i = 0; $i < $config['blank_lines_before_all_outputs']; $i++) {
                file_put_contents($outputFile, "\n", FILE_APPEND | LOCK_EX);
                if ($config['debug_to_stdout']) {
                    echo "\n";
                }
            }
        }
    }

    // Update lastStarted for the current debug call regardless of new run or not
    $state->lastStarted = $currentTime;

    // Print header once per run (if configured)
    // Only print if 'print_header_once_per_run' is true AND it hasn't been printed for the current run yet
    if (($config['print_header_once_per_run'] ?? false) && !($state->headerPrintedForCurrentRun ?? false)) {
        $finalOutput .= format_debug_header($config);
        $state->headerPrintedForCurrentRun = true; // Mark as printed for this run
    }

    // Save the state after all state modifications (lastStarted and headerPrintedForCurrentRun)
    save_state($stateFile, $state);

    // Handle blank lines between outputs (this applies to outputs within the same "run")
    if (($config['blank_lines_between_outputs'] ?? 0) > 0) {
        for ($i = 0; $i < $config['blank_lines_between_outputs']; $i++) {
            $finalOutput .= "\n";
        }
    }

    // Format output based on content type
    if (is_string($capture) && str_starts_with($capture, '**')) { // Special handling for method headers
        $finalOutput .= format_method_header($capture, $config);
    } else {
        // Pass the determined caller file and line to the formatting function
        $finalOutput .= format_variable_dump(
            $capture,
            $label,
            $config,
            $callerFile, // Pass the dynamically determined caller file
            $callerLine, // Pass the dynamically determined caller line
            $includeTrace ? $trace : null
        );
    }

    // Write to file
    file_put_contents($outputFile, $finalOutput, FILE_APPEND | LOCK_EX);

    // Debug to stdout
    if ($config['debug_to_stdout'] ?? false) {
        echo $finalOutput;
    }
}








// Command-Line Argument Handling

if (php_sapi_name() === 'cli') {
    global $argv;

    $config = get_config();

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

    // Fallback to General Test Execution
    if ($config['run_tests'] == 1) {

        // Test Data (Moved inside conditional block)
        $birds = ['blue', 'tit', 'pigeon', 'nested' => ['robin', 'sparrow']];
        $fruit = ['apple', 'banana', 'pear'];
        $cars = ['ford', 'peugeot', 'vauxhall'];

        // Test Function
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