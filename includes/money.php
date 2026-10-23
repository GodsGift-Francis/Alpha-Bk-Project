<?php
// DECIMAL(15,2), represented as strings at SQL boundaries and integer cents in PHP.
function moneyValue($value): string {
    if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
        throw new InvalidArgumentException('Enter an amount with at most two decimal places.');
    }
    $amount = $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    if (moneyCents($amount) <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }
    return $amount;
}

function moneyCents(string $value): int {
    if (PHP_INT_SIZE < 8) throw new RuntimeException('A 64-bit PHP runtime is required.');
    if (!preg_match('/^(-?)([0-9]{1,13})\.([0-9]{2})$/D', $value, $parts)) {
        throw new RuntimeException('Invalid stored monetary value.');
    }
    return ($parts[1] === '-' ? -1 : 1) * ((int)$parts[2] * 100 + (int)$parts[3]);
}
