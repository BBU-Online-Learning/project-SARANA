# Four-Role Presentation Script

Target duration: 12 minutes. Use staging-only accounts and non-sensitive sample files.

## Preparation

1. Create one Super Admin, one Admin, two Teachers and two Students through permitted account administration.
2. Complete onboarding for every demo account.
3. Create one active class owned by Teacher A with General and Announcement channels.
4. Enroll Teacher B as co-teacher and Student A as student; keep Student B outside initially.
5. Create one direct chat and one group owned by Student A.
6. Open separate normal/private browser profiles so sessions remain distinct.
7. Keep backup slides or screenshots available only as presentation fallback, not as proof of behavior.

## Demo Flow

### 1. Super Admin, 2 minutes

- Sign in through password and OTP.
- Show the real dashboard counts and privileged account administration link.
- Create or edit an Admin/Teacher/Student account.
- Explain that Super Admin cannot inspect private chat or class content without membership.

### 2. Admin, 2 minutes

- Show that Admin can manage Teacher and Student accounts but cannot manage Admin peers or Super Admin.
- Show class metadata administration.
- Attempt to open class messages before enrollment and show access is denied.

### 3. Teacher, 3 minutes

- Open the owned class, edit metadata, and show owner/co-teacher/student membership roles.
- Post an Announcement and a General-channel message.
- Add Student B, then archive the class and show history remains readable while posting is disabled.
- Restore the class.

### 4. Student, 3 minutes

- Show joined classes and conversations with real counts.
- Read the Announcement but show the absence of a student posting action there.
- Send a General-channel message and edit the student’s own message.
- Upload a safe image/document in group chat and retrieve it as a member.

### 5. Realtime Revocation, 2 minutes

- Keep Student B’s class or group page open in a second browser.
- Remove Student B from that membership.
- Show that the connected client loses future events and authorized HTTP/file access.
- Restart or briefly disconnect Reverb, send a message, reconnect, and show history catches up without duplicates.

## Closing Statement

The MVP delivers four fixed institution roles, secure onboarding and recovery, direct/group chat, owned classes with channels/announcements, private attachments, realtime synchronization, and server-enforced membership revocation. Calendar, assignments, grading, meetings, analytics, advanced notifications and file-manager features were intentionally excluded to finish a secure, demonstrable project.

## Rehearsal Checklist

- Run the full script twice before presentation day.
- Time each section and remove explanations before removing security checks.
- Test desktop and one narrow mobile viewport.
- Verify SMTP, queue worker and Reverb before each rehearsal.
- Reset demo messages/files between rehearsals without deleting schema or real history.
- Reserve two days for P0/P1 fixes, one day for regression, and freeze features 48 hours before the final demo.

