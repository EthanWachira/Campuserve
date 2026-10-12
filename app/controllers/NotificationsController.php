<?php
class NotificationsController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireLogin();
        $this->render('tickets/notifications', ['title' => 'Notifications', 'u' => $u, 'items' => Notification::recent((int) $u['id'], 40)]);
    }

    /** Opens one notification (marks it read, then follows its link) or marks everything read. */
    public function readAction(): void
    {
        $u = $this->requireLogin();
        if (!$this->isPost()) redirect('notifications/index');
        $this->requireCsrf();
        if (($_POST['all'] ?? '') === '1') {
            Notification::markAllRead((int) $u['id']);
            redirect('notifications/index');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $n = Database::one('SELECT link FROM notifications WHERE id = ? AND user_id = ?', [$id, $u['id']]);
        Notification::markRead((int) $u['id'], $id);
        if ($n && $n['link'] && preg_match('#^\?r=[a-z]+/[a-z]+(&id=\d+)?$#', $n['link'])) {
            header('Location: ' . link_url($n['link']));
            exit;
        }
        redirect('notifications/index');
    }
}
