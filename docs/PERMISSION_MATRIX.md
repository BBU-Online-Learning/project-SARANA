# Permission Matrix

## Institution Accounts

| Action | Super Admin | Admin | Teacher | Student |
| --- | --- | --- | --- | --- |
| View account administration | Yes | Yes | No | No |
| Create/manage Admin | Yes, except self-protection rules | No | No | No |
| Create/manage Teacher | Yes | Yes | No | No |
| Create/manage Student | Yes | Yes | No | No |
| Manage peer Admin | No | No | No | No |
| Change role definitions | No | No | No | No |
| Suspend/demote/delete last active Super Admin | No | No | No | No |
| Read private chats because of institution role | No | No | No | No |
| Read class messages because of institution role | No; enrollment required | No; enrollment required | No; enrollment required | Enrollment required |

## Classes

| Action | Institution Admin | Class Owner | Class Teacher | Class Student | Outsider |
| --- | --- | --- | --- | --- | --- |
| Create class | Yes, with eligible Teacher owner | Teacher creates self-owned class | Teacher creates self-owned class | No | No |
| Edit metadata | Yes | Yes | Yes | No | No |
| Archive/restore | Yes | Yes | No | No | No |
| Transfer ownership | Yes, to eligible Teacher | Yes, to eligible Teacher | No | No | No |
| Regenerate join code | No unless also owner | Yes | No | No | No |
| Add/remove class Teachers | Administrative enrollment within target limits | Yes | No | No | No |
| Add/remove Students | Administrative enrollment within target limits | Yes | Yes | No | No |
| Create/delete non-default channel | No unless enrolled as owner/Teacher | Yes | Yes | No | No |
| Delete default channel | No | No | No | No | No |
| Read/send channel messages | Only if explicitly enrolled | Yes | Yes | Yes, except Announcement posting | No |
| Post Announcement | Only if explicitly enrolled as owner/Teacher | Yes | Yes | No | No |
| Read archived history | If authorized by enrollment | Yes | Yes | Yes | No |
| Mutate archived class | Restore only | Restore only | No | No | No |

Only application-role Teachers who are active and fully onboarded may hold class `owner` or `teacher` membership. Membership roles are `owner`, `teacher`, and `student` and do not replace institution roles.

## Direct And Group Chat

| Action | Direct member | Group owner | Group member | Outsider/Admin not enrolled |
| --- | --- | --- | --- | --- |
| Read/send messages | Yes | Yes | Yes | No |
| Retrieve attachments | Yes while current member | Yes | Yes while current member | No |
| Rename group | Not applicable | Yes | No | No |
| Add/remove group members | Not applicable | Yes | No | No |
| Leave group | Not applicable | No until ownership is resolved | Yes | No |
| Edit/delete message | Own messages only | Own messages only | Own messages only | No |
| Receive future realtime events after removal | No | Not applicable | No | No |

Group membership roles used by the MVP are `owner` and `member`. Legacy `admin` rows grant no management capability.

