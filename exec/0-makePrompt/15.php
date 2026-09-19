<?php
$filename = $_SERVER['DOCUMENT_ROOT'] . '/prompts/15-freetalk.txt';
if (file_exists($filename)) {
    $prompt = file_get_contents($filename);
    echo $prompt;
} else {
    echo "Prompt file not found.";
}