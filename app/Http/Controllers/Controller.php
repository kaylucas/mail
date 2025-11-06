<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Sanitize error messages to prevent sensitive data exposure.
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        // Remove file paths (Unix and Windows style)
        $message = preg_replace('#[/\\\\][a-zA-Z0-9/_\-\\.\\\\]+\.(php|env|key|pem)#i', '[FILE_PATH]', $message);

        // Remove database connection strings
        $message = preg_replace('#(mysql|pgsql|mongodb|redis)://[^\s]+#i', '[DB_CONNECTION]', $message);

        // Remove email addresses
        $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $message);

        // Remove potential tokens and secrets (long alphanumeric strings)
        $message = preg_replace('/[a-zA-Z0-9]{32,}/', '[TOKEN]', $message);

        // Remove IP addresses
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP_ADDRESS]', $message);

        // Remove passwords from connection strings
        $message = preg_replace('/(password|pwd|pass)=[^\s;]+/i', '$1=[REDACTED]', $message);

        // Generic messages for common error types
        if (stripos($message, 'SQLSTATE') !== false || stripos($message, 'database') !== false || stripos($message, 'connection') !== false) {
            return 'A database error occurred. Please try again later.';
        }

        if (stripos($message, 'authentication') !== false || stripos($message, 'token') !== false || stripos($message, 'unauthorized') !== false) {
            return 'Authentication failed. Please log in again.';
        }

        if (stripos($message, 'permission') !== false || stripos($message, 'forbidden') !== false) {
            return 'Permission denied. Please contact support.';
        }

        // If message is too long or contains suspicious patterns, return generic error
        if (strlen($message) > 200 || preg_match('/(stack trace|thrown in|on line \d+)/i', $message)) {
            return 'An error occurred. Please try again later.';
        }

        return $message;
    }
}
