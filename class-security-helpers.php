<?php
if (!defined('ABSPATH')) exit;

/**
 * Security Helper Functions
 * Shared utilities for all security classes
 */
class EST_Security_Helpers
{
    /**
     * Get client IP address (supports Cloudflare, proxies, etc.)
     */
    public static function get_client_ip()
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if IP matches pattern (supports CIDR and wildcard)
     */
    public static function ip_matches($ip, $pattern)
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation (e.g., 192.168.1.0/24)
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            if ($ip_long === false || $subnet_long === false) {
                return false;
            }
            $mask_long = -1 << (32 - intval($mask));
            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        }

        // Wildcard (e.g., 192.168.1.*)
        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $pattern . '$/', $ip) === 1;
    }

    /**
     * Validate IP address or CIDR notation
     */
    public static function is_valid_ip_or_cidr($ip)
    {
        // Check if it's a valid IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if it's a valid CIDR
        if (strpos($ip, '/') !== false) {
            list($subnet, $mask) = explode('/', $ip);
            return filter_var($subnet, FILTER_VALIDATE_IP) &&
                is_numeric($mask) &&
                $mask >= 0 &&
                $mask <= 32;
        }

        // Check if it's a valid wildcard pattern
        if (preg_match('/^(\d{1,3}\.){0,3}\*$/', $ip)) {
            return true;
        }

        return false;
    }
}
