<?php

/**
 * =============================================================================
 * ANSI Code Demonstration Function
 * =============================================================================
 */

/**
 * Outputs a demonstration of various ANSI text styles, foreground colors,
 * and background colors. Requires a terminal that supports ANSI escape codes.
 */
function show_all_ansi_combinations(): void {
    $reset = "\033[0m";

    // Styles: code => name
    $styles = [
        '0' => 'Normal', // Technically reset, but good baseline
        '1' => 'Bold',
        // '2' => 'Dim', // Often not supported or same as normal
        // '3' => 'Italic', // Often not supported
        '4' => 'Underline',
        '5' => 'Blink (Slow)', // Often not supported or disabled
        // '6' => 'Blink (Fast)', // Often not supported or disabled
        '7' => 'Reverse/Inverse',
        // '8' => 'Hidden/Conceal', // Usually not useful for display
    ];

    // Basic Foreground Colors: code => name
    $fgColors = [
        '30' => 'Black', '31' => 'Red', '32' => 'Green', '33' => 'Yellow',
        '34' => 'Blue', '35' => 'Magenta', '36' => 'Cyan', '37' => 'White',
        // Bright/High-Intensity Foreground
        '90' => 'Bright Black (Gray)', '91' => 'Bright Red', '92' => 'Bright Green',
        '93' => 'Bright Yellow', '94' => 'Bright Blue', '95' => 'Bright Magenta',
        '96' => 'Bright Cyan', '97' => 'Bright White',
    ];

    // Basic Background Colors: code => name
    $bgColors = [
        '40' => 'Black BG', '41' => 'Red BG', '42' => 'Green BG', '43' => 'Yellow BG',
        //'44' => 'Blue BG', '45' => 'Magenta BG', '46' => 'Cyan BG', '47' => 'White BG',
        // Bright/High-Intensity Background
        //'100' => 'Bright Black BG', '101' => 'Bright Red BG', '102' => 'Bright Green BG',
        //'103' => 'Bright Yellow BG', '104' => 'Bright Blue BG', '105' => 'Bright Magenta BG',
        //'106' => 'Bright Cyan BG', '107' => 'Bright White BG',
    ];

    echo "\n--- ANSI Code Combination Demo ---\n";
    echo "(Note: Appearance depends on terminal support)\n\n";

    // Iterate through styles
    foreach ($styles as $styleCode => $styleName) {
        echo "--- Style: {$styleName} (Code: {$styleCode}) ---\n";

        // Iterate through foreground colors
        foreach ($fgColors as $fgCode => $fgName) {
            // Iterate through background colors
            foreach ($bgColors as $bgCode => $bgName) {
                // Construct the ANSI sequence: \033[STYLE;FG;BGm
                $sequence = "\033[{$styleCode};{$fgCode};{$bgCode}m";
                // Construct the literal string representation for display
                $sequenceLiteral = "\\033[{$styleCode};{$fgCode};{$bgCode}m"; // <<< Use double backslash for literal \
                $label = "{$styleName} + {$fgName} + {$bgName}";

                // Print the sample, including the literal sequence code
                echo str_pad($label, 55) . ": "
                    . $sequence . " Sample Text " . $reset // Apply the style
                    . " (" . $sequenceLiteral . ")" // Show the literal code
                    . "\n";
            }
            // Add a newline for better separation between foreground color groups
            echo "\n";
        }
        // Add a double newline for better separation between style groups
        echo "\n\n";
    }

    echo "--- ANSI Demo Finished ---\n";
}

show_all_ansi_combinations();