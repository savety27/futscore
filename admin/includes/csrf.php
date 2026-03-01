<?php

if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token(): string
    {
        if (
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            $_SESSION['csrf_token'] === ''
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('admin_csrf_is_valid')) {
    function admin_csrf_is_valid($token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

