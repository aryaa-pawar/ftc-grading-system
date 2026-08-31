# Authentication Integration

## Apply the migration

1. Back up the `ftc_grading` database.
2. Run `database_migration.sql` in phpMyAdmin or the MySQL client.
3. Confirm every active faculty row has a non-null `faculties.user_id`.
4. Confirm `users.email` is unique and each mapped user has a `profiles` row.
5. Replace any placeholder or weak legacy faculty passwords before production use.

The migration does not change `faculty_marks_entry.faculty_id` or any grading view. It adds only the identity link and authentication/RBAC tables.

## Permission checks

The shared functions are in `includes/auth.php`:

- `has_permission('marks.enter')` checks the logged-in user's role.
- `require_permission('marks.view')` protects a page and returns a 403 response when denied.
- Faculty sessions retain `faculty_id`, `faculty_name`, and `faculty_email`; modern keys are also populated.

Default permissions are seeded for guides, administrators, and verification officers. Adjust assignments in `role_permissions` for local policy.

## Verification checklist

1. Log in with an active faculty account and confirm an OTP is created in `otp_verifications`.
2. Verify the OTP and confirm `user_id`, `role_name`, and legacy `faculty_id` exist in the session.
3. Open internal, midterm, and final marks entry; save a mark and confirm the existing assignment and validation behavior.
4. Open result register, student result, tabulation, and download pages; confirm existing views return the same rows and totals.
5. Edit an internal mark as an administrator and confirm `admin_marks_audit` receives the existing audit record.
6. Try a guide session against an admin page and a verification session against marks entry; both must receive HTTP 403.
7. Confirm logout removes both modern and legacy session keys and a back-button request cannot access cached authenticated pages.
8. Re-run the migration on a backup copy to confirm the conditional legacy-column changes are safe to repeat.

The existing calculation views, including `v_paper_assessment_scores`, `v_tabulation`, and `v_result_register`, are intentionally not modified.