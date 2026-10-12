<?php
class Notification
{
    public static function send(int $userId, string $title, ?string $body = null, ?string $link = null): void
    {
        Database::exec('INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)', [$userId, $title, $body, $link]);
    }

    public static function recent(int $userId, int $limit = 6): array
    {
        return Database::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit, [$userId]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }

    public static function markRead(int $userId, int $id): void
    {
        Database::exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?', [$userId, $id]);
    }
}
