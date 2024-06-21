<?php

/**
 * Quick translate copier.
 * 
 * @category InternalTools
 * @package  None
 * @author   Wesley de Groot <OSS@wesleydegroot.nl>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/0xWDG/lproj-updater
 */

// get from ARGS
$to = "nl";

// This is terrible sorry. (it are a few lines.)
// Check if we have an argument
if (isset($argv[1])) {
    // We have an argument.
    // Does that translation exists?
    if (file_exists($argv[1] . ".lproj/Localizable.strings")) {
        // read argument, and set the translation to that.
        $to = $argv[1];
        // Print info
        print("[Info] Language selected, language is now: " . strtoupper($to) . PHP_EOL
        );
    } else {
        // Ok, it does not exists, try to recover
        print("[ERROR] Selected language does not exists; " . strtoupper($argv[1]) . PHP_EOL
        );
        print("[RECOVER] Creating files..." . PHP_EOL);

        // Can we create this directory?
        if (@mkdir($argv[1] . ".lproj")) {
            // Yup!
            print("[RECOVERED] Created directory." . PHP_EOL);

            // Can we create a empty file?
            if (@file_put_contents($argv[1] . ".lproj/Localizable.strings", "")) {
                // Yup, use it!
                print("[RECOVER] Created strings file." . PHP_EOL);
                $to = $argv[1];
            }
        } else {
            // We cannot make this directory, exit
            print("[ERROR] Cannot create directory " . strtoupper($argv[1]) . PHP_EOL);
            exit(1);
        }
    }
} else {
    // No argument passed.
    // Print info
    print("[Info] No language selected, defaulting to " . strtoupper($to) . PHP_EOL);
}
// Now is the readable part.

// Input, Output files
$inputFile = "en.lproj/Localizable.strings";
$outputFile = $to . ".lproj/Localizable.strings";

// Array for import translations
$translationsImport = array();

// Array for export translations
$translationsExport = array();

// Array for translation comments
$translationsComments = array();

// Load import as Array, second parameter = save comments.
$translationsImport = stringsToArray(file_get_contents($inputFile), true);

// Load export as Array
$translationsExport = stringsToArray(@file_get_contents($outputFile));

// Check Import <> Export, do not overwrite files.
foreach ($translationsImport as $key => $value) {
    // Does the translation NOT exists?
    if (!isset($translationsExport[$key])) {
        // Then append.
        $translationsExport[$key] = $value;
    }
}

// Create Localizable.strings
$localizablestrings = sprintf(
    "/*\n * Automatic generated translation file" .
        "\n * Do not edit\n *\n" .
        " * Created by Wesley de Groot, https://wesleydegroot.nl.\n" .
        " * Github: https://github.com/0xWDG/lproj-updater\n" .
        " *\n *\n * Generated @ %s\n * Input file: %s (%s strings)\n" .
        " * Output file: %s (%s strings)\n * Check: %s\n*/\n\n",
    // Current date (first %s)
    date("d-m-Y H:i:s"),
    // Input file path (second %s)
    $inputFile,
    // Input translation count (third %s)
    sizeof($translationsImport),
    // Output file path (fourth %s)
    $outputFile,
    // Output translation count (fifth %s)
    sizeof($translationsExport),
    // Is it ok? (sixth %s)
    sizeof($translationsImport) >= sizeof($translationsExport) ? "Pass" : "Fail"
);

// Walk trough the translations
foreach ($translationsExport as $kvKey => $kvVal) {
    // kv = keyvalue.
    $localizablestrings .= sprintf(
        // "kvKey" = "kvVal"\n\n
        "%s\n\"%s\" = \"%s\";\n\n",
        // Ok this maybe look strange
        (
            // Do we have saved a comment?
            !isset($translationsComments[$kvKey])
            // Nope, so generate one
            ? sprintf("/* [Auto generated translation string]: %s */", $kvKey)
            // Yes, use the saved comment
            : $translationsComments[$kvKey]
        ),
        // Key
        $kvKey,
        // Value
        $kvVal
    );
}

// Remove all the null bytes.
$localizablestrings = str_replace("\0", "", $localizablestrings);
// If input count equals export count, then SAVE :D
if (sizeof($translationsExport) >= sizeof($translationsImport)) {
    // Save the translation
    file_put_contents(
        $outputFile,
        // Condert to UTF-8
        utf8_encode($localizablestrings)
    );
    // Say we have saved it.
    print("Updated translation file {$outputFile}" . PHP_EOL);
    // Print out some stats.
    print_r(
        array(
            '$inputFile' => $inputFile,
            'input count' => sizeof($translationsImport),
            '$outputFile' => $outputFile,
            'export count' => sizeof($translationsExport),
            'exportFile' => $localizablestrings
        )
    );

    // No error
    exit(0);
} else {
    // Oops, something is wrong
    print("ERROR: IMPORT DOES NOT EQUALS EXPORT STRING COUNT" . PHP_EOL);

    // Print out debug information
    print("Debug information below" . PHP_EOL);
    print_r(
        array(
            '$inputFile' => $inputFile,
            'input count' => sizeof($translationsImport),
            '$outputFile' => $outputFile,
            'export count' => sizeof($translationsExport),
            'exportFile' => $localizablestrings
        )
    );

    // Error
    exit(1);
}

/**
 * Convert a string to an array.
 *
 * @param string $input        The string to convert.
 * @param bool   $saveComments Save comments?
 *
 * @return array
 */
function stringsToArray($input, $saveComments = false)
{
    // Make our translation comments array global
    global $translationsComments;

    // Remove UTF-16 BOM (Byte Order Mark)
    $input = preg_replace("/\xff\xfe/", '', $input);

    // Setup an empty array
    $translations = array();

    // Structure is:
    // /* comment */
    // "" = ""
    //
    // /* comment */
    // "" = ""

    // Explode all newlines
    $inputItems = explode("\n", $input);
    // Trick, because the comment is above the current line.
    // So it always needs to be one lower.
    $commentCounter = -1;
    // Walk to the parsed items.
    foreach ($inputItems as $item) {
        // UTF-8 || UTF-16
        if (substr($item, 0, 1) == "\"" || substr($item, 1, 1) == "\"") {
            // Split all "s
            $kvSplitter = explode("\"", $item);
            // Translation is as follows:
            //
            // "AA" = "AAA";
            // [0] = BOM (or \r)
            // [1] = KEY
            // [2] = SPACE = SPACE
            // [3] = VALUE
            // [4] = ;
            // Something is wrong
            if (sizeof($kvSplitter) > 5) {
                echo "THE SIZE OF THE SPLIT FUNCTION IS NOT CORRECT" . PHP_EOL;
                echo "PLEASE DO NOT USE THE \" CHARACTER IN TRANSLATIONS" . PHP_EOL;
                echo "I WILL NOT CONTINUE";
                exit(1);
            }
            // Save the comments
            if ($saveComments) {
                // Save the comment.
                $translationsComments[$kvSplitter[1]] = $inputItems[$commentCounter];
            }
            // Save the translation
            $translations[$kvSplitter[1]] = $kvSplitter[3];
        }
        // Count up
        $commentCounter++;
    }
    // Return the extracted translations
    return $translations;
}
