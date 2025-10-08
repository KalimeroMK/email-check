<?php

require_once 'vendor/autoload.php';

use KalimeroMK\EmailCheck\EmailValidator;

// Create validator without mock (will use real DNS validator)
$validator = new EmailValidator();

// Test 1: Missing domain
echo "Test 1: Missing domain (test@)\n";
$result1 = $validator->validate('test@');
echo "Errors: " . json_encode($result1['errors']) . "\n";
echo "Is valid: " . ($result1['is_valid'] ? 'true' : 'false') . "\n\n";

// Test 2: Too long email
echo "Test 2: Too long email\n";
$longLocalPart = str_repeat('a', 250);
$email2 = $longLocalPart . '@example.com';
echo "Email length: " . strlen($email2) . " characters\n";
$result2 = $validator->validate($email2);
echo "Errors: " . json_encode($result2['errors']) . "\n";
echo "Is valid: " . ($result2['is_valid'] ? 'true' : 'false') . "\n\n";