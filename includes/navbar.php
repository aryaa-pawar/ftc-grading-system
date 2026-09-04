<?php
/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Common Navigation Bar
|--------------------------------------------------------------------------
*/
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isVerificationPanel = strpos($currentPath, '/verification/') !== false;
$appBase = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $_SERVER['SCRIPT_NAME'] ?? '');
if ($appBase === '/' || $appBase === '\\') {
    $appBase = '';
}
require_once __DIR__ . '/csrf.php';

$adminNotifications = [];
$adminUnreadNotificationCount = 0;
$scrutinyNotifications = [];
$scrutinyUnreadNotificationCount = 0;
if (!$isVerificationPanel) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $adminUnreadNotificationCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
        $adminNotifications = $pdo->query("
            SELECT
                an.verification_status,
                an.message,
                an.created_at,
                an.entry_id,
                s.student_name,
                s.roll_no,
                b.batch_name,
                sub.subject_name,
                at.assessment_type_name
            FROM admin_notifications an
            INNER JOIN students s ON s.student_id = an.student_id
            INNER JOIN batches b ON b.batch_id = an.batch_id
            LEFT JOIN faculty_marks_entry fme ON fme.entry_id = an.entry_id
            LEFT JOIN subjects sub ON sub.subject_id = fme.subject_id
            LEFT JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
            ORDER BY an.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $adminNotifications = [];
    }
} else {
    require_once __DIR__ . '/../config/database.php';
    $scrutinyUser = function_exists('current_user') ? current_user() : null;
    if ($scrutinyUser) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM scrutiny_notifications WHERE recipient_user_id = :user_id AND is_read = 0');
            $stmt->execute([':user_id' => $scrutinyUser['user_id']]);
            $scrutinyUnreadNotificationCount = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT sn.notification_id, sn.case_id, sn.old_marks, sn.new_marks, sn.status, sn.is_read, sn.created_at, s.student_name, s.roll_no, sub.subject_name FROM scrutiny_notifications sn INNER JOIN students s ON s.student_id = sn.student_id INNER JOIN subjects sub ON sub.subject_id = sn.subject_id WHERE sn.recipient_user_id = :user_id ORDER BY sn.created_at DESC LIMIT 20");
            $stmt->execute([':user_id' => $scrutinyUser['user_id']]);
            $scrutinyNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $scrutinyNotifications = [];
        }
    }
}
?>

<nav style="
    width: 100%;
    max-width: 100%;
    height: 60px;
    background-color: #1f4e79;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    box-sizing: border-box;
    gap: 16px;
">

    <div style="
        font-size: 23px;
        font-weight: bold;
        min-width: 0;
        overflow-wrap: anywhere;
    ">
        FTC Grading & Tabulation Management System
        <?php if ($isVerificationPanel): ?>
            <details class="admin-notification-menu" style="display:inline-block;position:relative;margin-left:20px;">
                <summary style="list-style:none;cursor:pointer;font-size:20px;line-height:1;" title="Scrutiny Notifications">&#128276;<?php if ($scrutinyUnreadNotificationCount > 0): ?><span style="font-size:11px;background:#dc3545;border-radius:9px;padding:2px 6px;vertical-align:top;"><?php echo $scrutinyUnreadNotificationCount; ?></span><?php endif; ?></summary>
                <div style="position:absolute;left:0;top:28px;width:360px;max-height:430px;overflow-y:auto;background:#fff;color:#222;border:1px solid #d5d5d5;z-index:1000;text-align:left;">
                    <div style="padding:12px;border-bottom:1px solid #eee;color:#1f4e79;">Scrutiny Notifications</div>
                    <?php foreach ($scrutinyNotifications as $notification): ?>
                        <a href="<?php echo htmlspecialchars($appBase . '/verification/edit_scrutiny.php?case_id=' . (int)$notification['case_id'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); fetch('<?php echo htmlspecialchars($appBase . '/verification/notification_action.php', ENT_QUOTES, 'UTF-8'); ?>',{method:'POST',body:new URLSearchParams({csrf_token:'<?php echo rawurlencode(generateCsrfToken()); ?>',notification_id:'<?php echo (int)$notification['notification_id']; ?>',case_id:'<?php echo (int)$notification['case_id']; ?>',action:'read'})}).finally(function(){window.location.href=this.href;}.bind(this));" style="display:block;padding:11px 14px;border-bottom:1px solid #eee;font-size:12px;<?php echo !$notification['is_read'] ? 'font-weight:bold;' : ''; ?>">
                            <?php echo !$notification['is_read'] ? '&#9679; ' : ''; ?>Marks Updated<br><?php echo htmlspecialchars($notification['student_name'] . ' - ' . $notification['subject_name'], ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars((string)$notification['old_marks']); ?> &rarr; <?php echo htmlspecialchars((string)$notification['new_marks']); ?> | Awaiting your re-check<br><small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
        <?php if (!$isVerificationPanel): ?>
            <details class="admin-notification-menu" style="position:absolute; right:145px; top:19px; z-index:1000;">
                <summary style="list-style:none; cursor:pointer; color:#ffffff; font-size:20px; line-height:1;" title="Notifications">
                    &#128276;
                    <?php if ($adminUnreadNotificationCount > 0): ?>
                        <span style="position:absolute; top:-9px; right:-10px; min-width:18px; height:18px; padding:1px 5px; border-radius:9px; background:#dc3545; color:#ffffff; font-size:11px; font-weight:bold; text-align:center; line-height:16px;">
                            <?php echo $adminUnreadNotificationCount; ?>
                        </span>
                    <?php endif; ?>
                </summary>
                <div style="position:absolute; right:0; top:30px; z-index:1000; width:360px; max-height:430px; overflow-y:auto; background:#ffffff; color:#222222; border:1px solid #d5d5d5; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,0.2); text-align:left;">
                    <div style="padding:12px 14px; border-bottom:1px solid #eeeeee; font-weight:bold; color:#1f4e79;">Verification Notifications</div>
                    <?php if (empty($adminNotifications)): ?>
                        <div style="padding:18px 14px; color:#777; font-size:13px;">No notifications yet.</div>
                    <?php else: ?>
                        <?php foreach ($adminNotifications as $notification): ?>
                            <div style="padding:11px 14px; border-bottom:1px solid #eeeeee; font-size:12px;">
                                <strong><?php echo htmlspecialchars($notification['student_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span style="color:#666;">(<?php echo htmlspecialchars($notification['roll_no'], ENT_QUOTES, 'UTF-8'); ?>)</span><br>
                                <?php if ($notification['verification_status'] === 'correct'): ?>
                                    <span style="color:#087f3e; font-weight:bold;">&#10003; Correct</span>
                                <?php else: ?>
                                    <span style="color:#b02a37; font-weight:bold;">&#10007; Wrong</span>
                                <?php endif; ?>
                                <span style="color:#666;"> | <?php echo htmlspecialchars($notification['batch_name'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <?php if (!empty($notification['subject_name'])): ?><small style="color:#666;"><?php echo htmlspecialchars($notification['subject_name'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($notification['assessment_type_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small><br><?php endif; ?>
                                <small style="color:#777;"><?php echo htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
    </div>

    <div style="
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 16px;
        flex-shrink: 0;
    ">
        <?php echo $isVerificationPanel ? 'Scrutiny Officer' : 'Admin'; ?>
        <?php if ($isVerificationPanel): ?>
            <a
                href="<?php echo htmlspecialchars($appBase . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>"
                style="
                    color: #ffffff;
                    text-decoration: none;
                    border: 1px solid rgba(255,255,255,0.75);
                    border-radius: 4px;
                    padding: 6px 12px;
                "
            >
                Logout
            </a>
        <?php endif; ?>
    </div>

</nav>

<style>
    @media (max-width: 600px) {
        nav {
            height: auto !important;
            min-height: 60px;
            padding: 10px 16px !important;
        }

        nav > div:first-child {
            font-size: 16px !important;
        }
    }
</style>

<?php if (!$isVerificationPanel && $adminUnreadNotificationCount > 0): ?>
    <script>
        document.querySelectorAll('.admin-notification-menu').forEach(function (notificationMenu) {
            notificationMenu.addEventListener('toggle', function () {
                if (!notificationMenu.open) {
                    return;
                }

                fetch('<?php echo htmlspecialchars($appBase . '/admin/read_notifications.php', ENT_QUOTES, 'UTF-8'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'csrf_token=<?php echo rawurlencode(generateCsrfToken()); ?>'
                }).then(function () {
                    var badge = notificationMenu.querySelector('span');
                    if (badge) {
                        badge.remove();
                    }
                });
            });
        });
    </script>
<?php endif; ?>
