<?php
/** Read-only for now: the booking workflow is part of the next build phase. */
class Appointment
{
    public static function upcomingForStudent(int $studentId, int $limit = 5): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS lecturer_name FROM appointments a JOIN users u ON u.id = a.lecturer_id
              WHERE a.student_id = ? AND a.starts_at >= NOW() AND a.status IN ("pending","confirmed")
              ORDER BY a.starts_at LIMIT ' . (int) $limit, [$studentId]);
    }

    public static function upcomingForLecturer(int $lecturerId, int $limit = 8): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS student_name, u.identifier AS student_no FROM appointments a JOIN users u ON u.id = a.student_id
              WHERE a.lecturer_id = ? AND a.starts_at >= NOW() AND a.status IN ("pending","confirmed")
              ORDER BY a.starts_at LIMIT ' . (int) $limit, [$lecturerId]);
    }

    public static function availability(int $lecturerId): array
    {
        return Database::all('SELECT day_of_week, start_time, end_time FROM lecturer_availability WHERE lecturer_id = ? ORDER BY day_of_week', [$lecturerId]);
    }

    public static function countsForLecturer(int $lecturerId): array
    {
        $rows = Database::all('SELECT status, COUNT(*) n FROM appointments WHERE lecturer_id = ? GROUP BY status', [$lecturerId]);
        return array_column($rows, 'n', 'status');
    }
}
