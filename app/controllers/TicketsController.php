<?php
class TicketsController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireLogin();
        $filters = [
            'status'   => (string) ($_GET['status'] ?? ''),
            'category' => (int) ($_GET['category'] ?? 0),
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'review'   => !empty($_GET['review']),
            'mine'     => !empty($_GET['mine']),
        ];
        $result = Ticket::search($u, $filters, (int) ($_GET['page'] ?? 1));
        $this->render('tickets/index', [
            'title' => $u['role'] === 'student' ? 'My requests' : 'Requests',
            'u' => $u, 'filters' => $filters, 'result' => $result, 'categories' => Ticket::categories(),
        ]);
    }

    public function createAction(): void
    {
        $u = $this->requireRole('student');

        if ($this->isPost()) {
            $this->requireCsrf();
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $errors = [];
            if (mb_strlen($subject) < 5 || mb_strlen($subject) > 150) $errors['subject'] = 'Give your request a short title (5 to 150 characters).';
            if (mb_strlen($description) < 15) $errors['description'] = 'Describe the problem in at least 15 characters so we can route it correctly.';
            if (mb_strlen($description) > 3000) $errors['description'] = 'Keep the description under 3,000 characters.';
            $upload = $_FILES['attachment'] ?? null;
            $uploadError = $this->validateAttachment($upload);
            if ($uploadError !== null) $errors['attachment'] = $uploadError;
            if ($errors) {
                with_old(['subject' => $subject, 'description' => $description]);
                $_SESSION['errors'] = $errors;
                redirect('tickets/create');
            }
            $cls = MLClient::classify($subject, $description);
            [$id, $ref] = Ticket::create($u, $subject, $description, $cls);
            if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $this->saveAttachment($id, (int) $u['id'], $upload);
                } catch (Throwable $e) {
                    error_log('CampuServe attachment save failed: ' . $e->getMessage());
                    flash('error', 'Your request was submitted, but the attachment could not be saved. You can submit it again in a reply.');
                }
            }
            Auth::audit((int) $u['id'], 'ticket_created', $ref . ' via ' . $cls['source']);
            clear_old();
            flash('success', "Request $ref submitted. It was routed automatically; you will be notified when it changes.");
            redirect('tickets/show', ['id' => $id]);
        }

        $errors = $_SESSION['errors'] ?? []; unset($_SESSION['errors']);
        $this->render('tickets/create', ['title' => 'New request', 'u' => $u, 'errors' => $errors, 'categories' => Ticket::categories()]);
        clear_old();
    }

    public function showAction(): void
    {
        $u = $this->requireLogin();
        $t = Ticket::find((int) ($_GET['id'] ?? 0));
        if (!$t || !Ticket::canView($u, $t)) App::notFound();          // 404, not 403: do not reveal that the ticket exists
        $manage = Ticket::canManage($u, $t);
        $this->render('tickets/show', [
            'title' => $t['reference_no'], 'u' => $u, 't' => $t, 'manage' => $manage,
            'updates' => Ticket::updates((int) $t['id'], $manage),
            'categories' => Ticket::categories(),
            'assignees' => $manage ? Ticket::assignees((int) $t['department_id']) : [],
            'attachments' => Ticket::attachments((int) $t['id']),
        ]);
    }

    /** Download a ticket attachment only after checking the current user's ticket access. */
    public function downloadAction(): void
    {
        $u = $this->requireLogin();
        $attachment = Ticket::attachment((int) ($_GET['id'] ?? 0));
        if (!$attachment) App::notFound();
        $ticket = Ticket::find((int) $attachment['ticket_id']);
        if (!$ticket || !Ticket::canView($u, $ticket)) App::notFound();

        $path = BASE_PATH . '/storage/ticket_attachments/' . basename($attachment['stored_name']);
        if (!is_file($path)) App::notFound();
        $mime = $attachment['mime_type'] ?: 'application/octet-stream';
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($attachment['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function validateAttachment(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return 'The attachment upload failed. Please try again.';
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) return 'The attachment must be 5 MB or smaller.';
        if (!is_uploaded_file($file['tmp_name'] ?? '')) return 'The uploaded file could not be verified.';
        $allowed = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/zip' => ['docx'], // Some Windows/PHP fileinfo versions identify DOCX as ZIP.
            'application/msword' => ['doc'],
            'application/x-ole-storage' => ['doc'],
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset($allowed[$mime]) || !in_array($ext, $allowed[$mime], true)) {
            return 'Allowed file types: PDF, JPG, PNG, GIF, DOC and DOCX.';
        }
        return null;
    }

    private function saveAttachment(int $ticketId, int $userId, array $file): void
    {
        $directory = BASE_PATH . '/storage/ticket_attachments';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create attachment storage directory.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $stored = bin2hex(random_bytes(24)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $stored)) {
            throw new RuntimeException('Could not move uploaded attachment.');
        }
        try {
            Database::exec(
                'INSERT INTO ticket_attachments (ticket_id, uploaded_by, original_name, stored_name, mime_type, file_size) VALUES (?,?,?,?,?,?)',
                [$ticketId, $userId, basename((string) $file['name']), $stored, $mime, (int) $file['size']]
            );
        } catch (Throwable $e) {
            @unlink($directory . '/' . $stored);
            throw $e;
        }
    }

    public function updateAction(): void
    {
        $u = $this->requireLogin();
        if (!$this->isPost()) redirect('tickets/index');
        $this->requireCsrf();
        $t = Ticket::find((int) ($_POST['id'] ?? 0));
        if (!$t || !Ticket::canView($u, $t)) App::notFound();

        $action = (string) ($_POST['action'] ?? '');
        $message = trim((string) ($_POST['message'] ?? ''));
        if (mb_strlen($message) > 2000) $message = mb_substr($message, 0, 2000);
        $back = ['id' => $t['id']];

        if ($u['role'] === 'student') {
            // Students may reply, and may close a request they consider resolved.
            if ($action === 'reply' && $message !== '' && !in_array($t['status'], ['closed'], true)) {
                Ticket::addUpdate((int) $t['id'], (int) $u['id'], $message);
                if ($t['assigned_to']) Notification::send((int) $t['assigned_to'], 'Reply on ' . $t['reference_no'], mb_strimwidth($message, 0, 140, '...'), '?r=tickets/show&id=' . $t['id']);
                flash('success', 'Reply sent.');
            } elseif ($action === 'close' && $t['status'] === 'resolved') {
                Ticket::changeStatus($t, 'closed', $u, 'Closed by the student.');
                flash('success', 'Request closed. Thank you.');
            } elseif ($action === 'reopen' && in_array($t['status'], ['resolved', 'closed'], true)) {
                Database::exec('UPDATE tickets SET status = "open", resolved_at = NULL WHERE id = ?', [$t['id']]);
                Ticket::addUpdate((int) $t['id'], (int) $u['id'], $message !== '' ? $message : 'Reopened by the student.', $t['status'], 'open');
                foreach (User::staffIn((int) $t['department_id']) as $s) Notification::send((int) $s['id'], 'Reopened: ' . $t['reference_no'], $t['subject'], '?r=tickets/show&id=' . $t['id']);
                flash('success', 'Request reopened.');
            } else {
                flash('error', 'That action is not available for this request.');
            }
            redirect('tickets/show', $back);
        }

        if (!Ticket::canManage($u, $t)) App::notFound();
        switch ($action) {
            case 'status':
                // Staff may attach a file to an update the student can see (same rules as the student upload).
                $upload = $_FILES['attachment'] ?? null;
                $uploadError = $this->validateAttachment($upload);
                if ($uploadError !== null) { flash('error', $uploadError); redirect('tickets/show', $back); }
                if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $this->saveAttachment((int) $t['id'], (int) $u['id'], $upload);
                        $message = trim($message . "\n\nAttached file: " . basename((string) $upload['name']));
                    } catch (Throwable $e) {
                        error_log('CampuServe attachment save failed: ' . $e->getMessage());
                        flash('error', 'The attachment could not be saved. Please try again.');
                    }
                }
                $new = (string) ($_POST['status'] ?? '');
                if (isset(TICKET_STATUSES[$new]) && $new !== $t['status']) {
                    Ticket::changeStatus($t, $new, $u, $message);
                    flash('success', 'Status changed to ' . status_label($new) . '.');
                } elseif ($message !== '') {
                    Ticket::addUpdate((int) $t['id'], (int) $u['id'], $message);
                    Notification::send((int) $t['student_id'], 'Update on ' . $t['reference_no'], mb_strimwidth($message, 0, 140, '...'), '?r=tickets/show&id=' . $t['id']);
                    flash('success', 'Reply sent to the student.');
                }
                break;
            case 'note':
                if ($message !== '') { Ticket::addUpdate((int) $t['id'], (int) $u['id'], $message, null, null, true); flash('success', 'Internal note saved.'); }
                break;
            case 'assign':
                if (Ticket::assign($t, (int) ($_POST['assignee'] ?? 0), $u)) flash('success', 'Assignment updated.');
                else flash('error', 'That person cannot be assigned to this request.');
                break;
            case 'confirm_category':
                Ticket::confirmCategory($t, $u);
                flash('success', 'Category confirmed.');
                break;
            case 'reclassify':
                if (Ticket::reclassify($t, (int) ($_POST['category'] ?? 0), $u)) {
                    flash('success', 'Category updated. Thank you; corrections improve the classifier.');
                    $after = Ticket::find((int) $t['id']);
                    if (!Ticket::canView($u, $after)) { flash('success', 'The request moved to ' . $after['department_name'] . '.'); redirect('tickets/index'); }
                }
                break;
            default:
                flash('error', 'Unknown action.');
        }
        redirect('tickets/show', $back);
    }
}
