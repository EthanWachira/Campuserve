<?php
/** Small key/value store for switches the administrator can change (e.g. automatic assignment). */
class Settings
{
    public static function get(string $name, string $default = '0'): string
    {
        try {
            $v = Database::value('SELECT setting_value FROM settings WHERE name = ?', [$name]);
            return $v === null ? $default : (string) $v;
        } catch (PDOException $e) {
            return $default;           // table not created yet: behave as if the switch is off
        }
    }

    public static function set(string $name, string $value): void
    {
        Database::exec('INSERT INTO settings (name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$name, $value]);
    }
}
