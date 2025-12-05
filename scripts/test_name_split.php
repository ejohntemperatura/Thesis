<?php
/**
 * Test script to verify name splitting logic
 * Run this before the actual migration to see how names will be split
 */

// Test cases
$testNames = [
    'John Doe',
    'Mary Jane Smith',
    'Pedro',
    'Juan dela Cruz Santos',
    'Maria Clara',
    'Jose Rizal Mercado y Alonso Realonda'
];

echo "Name Splitting Test\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($testNames as $fullName) {
    $nameParts = explode(' ', trim($fullName));
    
    $firstName = '';
    $middleName = '';
    $lastName = '';
    
    if (count($nameParts) == 1) {
        // Only one name part - treat as first name
        $firstName = $nameParts[0];
    } elseif (count($nameParts) == 2) {
        // Two parts - first and last name
        $firstName = $nameParts[0];
        $lastName = $nameParts[1];
    } else {
        // Three or more parts - first, middle(s), last
        $firstName = $nameParts[0];
        $lastName = array_pop($nameParts);
        array_shift($nameParts); // Remove first name
        $middleName = implode(' ', $nameParts);
    }
    
    echo "Original: {$fullName}\n";
    echo "  First Name:  {$firstName}\n";
    echo "  Middle Name: {$middleName}\n";
    echo "  Last Name:   {$lastName}\n";
    echo "  Reconstructed: " . trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName) . "\n";
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "Test completed!\n";
