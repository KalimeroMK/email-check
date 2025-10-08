<?php

echo "Starting update of temporary domains list...\n";

$sourceUrl = 'https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json';
$destinationFile = __DIR__ . '/vendor/kalimeromk/email-check/src/data/disposable.php';

$jsonContent = file_get_contents($sourceUrl);

if ($jsonContent === false) {
    die("Failed to fetch list from URL.\n");
}

$domainsArray = json_decode($jsonContent, true);

if (!is_array($domainsArray)) {
    die("Failed to decode JSON.\n");
}

$phpCode = "<?php\n\nreturn " . var_export($domainsArray, true) . ";\n";

$result = file_put_contents($destinationFile, $phpCode);

if ($result === false) {
    die(sprintf('Failed to write to file: %s%s', $destinationFile, PHP_EOL));
}

echo "Successfully updated list with " . count($domainsArray) . " domains.\n";
