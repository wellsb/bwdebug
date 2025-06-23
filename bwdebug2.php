<?php

// config
function get_config(): array {
    return [
        // --- File Paths ---
        'log_dir' => __DIR__ . '/logs', // Base directory for logs
        'default_output_file_name' => 'output.log',
        'second_output_file_name' => 'output2.log',
        'state_file_name' => 'state.json',

        // --- Output Formatting ---
        'output_method' => 'var_dump', // 'var_dump', 'print_r', 'var_export'
        'show_caller_info' => true, // Control display of caller file/line
        'print_header_once_per_run' => true, // Control whether to print the debug header only once per "run"
        'suppress_native_location' => false, // Suppress the (bwdebug:LINE),  location info added by var_dump/Xdebug
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
        'method_header_show_path' => true, // Include the file path in method headers
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
 * bwdebug($myVar, "My Variable");                                // Output with label
 * bwdebug(null, null, 1, false, true);                           // AUTO-GENERATE a method header
 * bwdebug("My Header", null, 1, false, true);                    // Manually specify a method header
 * bwdebug("**My Legacy Header");                                 // Legacy method header
 *
 * @param mixed|null $capture The variable or message to debug. Set to null for auto method header.
 * @param string|null $label An optional label for the output.
 * @param int $fileNum Optional log file number (1 or 2).
 * @param bool $includeTrace Whether to include a stack trace in the output.
 * @param bool $isMethodHeader Treat as a method header. If $capture is null, auto-generates it.
 */
function bwdebug(
    $capture,
    ?string $label = null,
    int $fileNum = 1,
    bool $includeTrace = false,
    bool $isMethodHeader = false
): void {

    // Identify the caller's context.
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

    $callerFile = '[unknown file]';
    $callerLine = '[unknown line]';
    $callerFunction = '[unknown function]';
    $callerClass = '';

    // Find the call to 'bwdebug' in the trace to get the correct file and line.
    $bwdebugFrameIndex = -1;
    foreach ($trace as $i => $frame) {
        // We look for the first frame that is a call to 'bwdebug'.
        // This is more reliable than checking file names.
        if (isset($frame['function']) && $frame['function'] === 'bwdebug') {
            $bwdebugFrameIndex = $i;
            break;
        }
    }

    if ($bwdebugFrameIndex !== -1) {
        // The frame for the bwdebug call itself contains the file and line of the call site.
        $callerFrame = $trace[$bwdebugFrameIndex];
        $callerFile = $callerFrame['file'] ?? '[unknown file]';
        $callerLine = $callerFrame['line'] ?? '[unknown line]';

        // The *next* frame in the trace holds the context (the function/class) that contained the call.
        $contextFrameIndex = $bwdebugFrameIndex + 1;
        if (isset($trace[$contextFrameIndex])) {
            $contextFrame = $trace[$contextFrameIndex];
            $callerFunction = $contextFrame['function'] ?? '[global]';
            $callerClass = $contextFrame['class'] ?? '';
        } else {
            // If there is no next frame, the call was from the global scope.
            $callerFunction = '[global]';
        }
    }

    $config = get_config(); // Ensure config is loaded

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

    $finalOutput = ""; // This initial $finalOutput

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

    // Determine if this is a header call (new flag, auto-gen, or legacy prefix)
    $isHeaderCall = $isMethodHeader || (is_string($capture) && str_starts_with($capture, '**'));

    if ($isHeaderCall) {
        $headerString = is_string($capture) ? $capture : '';

        // If the header flag is set but the capture is empty/null, auto-generate the string.
        if ($isMethodHeader && empty($headerString)) {
            $headerParts = [];
            // Use config to decide whether to show the full path or just the basename
            $headerParts[] = ($config['method_header_show_path'] ?? false) ? $callerFile : basename($callerFile);

            if (!empty($callerClass)) {
                $headerParts[] = $callerClass;
            }
            $headerParts[] = $callerFunction . '():' . $callerLine;
            $headerString = implode('#', $headerParts);
        }

        $finalOutput .= format_method_header($headerString, $config);
    } else {
        // This is a standard variable dump
        $finalOutput .= format_variable_dump(
            $capture,
            $label,
            $config,
            $callerFile,
            $callerLine,
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

} // End CLI check