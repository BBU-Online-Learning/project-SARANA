# ER Diagram

The diagram reflects the persisted collaboration core. Framework cache, job, session and password-reset tables are omitted. Call tables remain in the schema as legacy data but are outside the release MVP.

```mermaid
erDiagram
    ROLES ||--o{ USERS : assigns
    USERS ||--o{ CHAT_ROOMS : creates
    USERS ||--o{ CHAT_ROOM_MEMBERS : joins
    CHAT_ROOMS ||--o{ CHAT_ROOM_MEMBERS : contains
    CHAT_ROOMS ||--o{ MESSAGES : contains
    USERS ||--o{ MESSAGES : sends
    MESSAGES o|--o{ MESSAGES : replies_to
    MESSAGES ||--o{ MESSAGE_READS : read_by
    USERS ||--o{ MESSAGE_READS : records
    MESSAGES ||--o{ ATTACHMENTS : owns
    CHAT_ROOMS ||--o{ ATTACHMENTS : scopes
    USERS ||--o{ ATTACHMENTS : uploads
    ATTACHMENTS ||--o{ MEDIA : media_library
    MESSAGES ||--o{ MESSAGE_USER_DELETIONS : hidden_by
    USERS ||--o{ MESSAGE_USER_DELETIONS : hides
    MESSAGES ||--o{ MESSAGE_REACTIONS : reacts
    USERS ||--o{ MESSAGE_REACTIONS : reacts

    USERS ||--o{ SCHOOL_CLASSES : creates
    SCHOOL_CLASSES ||--o{ SCHOOL_CLASS_MEMBERS : contains
    USERS ||--o{ SCHOOL_CLASS_MEMBERS : joins
    SCHOOL_CLASSES ||--o{ SCHOOL_CLASS_CHANNELS : contains
    USERS ||--o{ SCHOOL_CLASS_CHANNELS : creates
    SCHOOL_CLASS_CHANNELS ||--o{ SCHOOL_CLASS_CHANNEL_MESSAGES : contains
    USERS ||--o{ SCHOOL_CLASS_CHANNEL_MESSAGES : sends
    SCHOOL_CLASSES ||--o{ CLASS_MEMBERSHIP_AUDITS : audited

    ROLES {
        bigint id PK
        string name
        boolean status
        timestamp deleted_at
    }
    USERS {
        bigint id PK
        bigint role_id FK
        string email UK
        string password
        enum status
        text two_factor_secret_encrypted
        bigint auth_version
        timestamp deleted_at
    }
    CHAT_ROOMS {
        bigint id PK
        enum type
        bigint created_by FK
        string name
        timestamp deleted_at
    }
    CHAT_ROOM_MEMBERS {
        bigint id PK
        bigint room_id FK
        bigint user_id FK
        enum role
        timestamp joined_at
    }
    MESSAGES {
        bigint id PK
        bigint room_id FK
        bigint sender_id FK
        uuid client_uuid
        text body
        timestamp deleted_at
    }
    ATTACHMENTS {
        bigint id PK
        bigint message_id FK
        bigint room_id FK
        bigint uploaded_by FK
        string original_name
        string storage_path
    }
    MEDIA {
        bigint id PK
        string model_type
        bigint model_id
        string disk
        string file_name
    }
    SCHOOL_CLASSES {
        bigint id PK
        bigint created_by FK
        string join_code UK
        timestamp archived_at
        timestamp deleted_at
    }
    SCHOOL_CLASS_MEMBERS {
        bigint id PK
        bigint school_class_id FK
        bigint user_id FK
        enum role
    }
    SCHOOL_CLASS_CHANNELS {
        bigint id PK
        bigint school_class_id FK
        bigint created_by FK
        string slug
        boolean is_default
        timestamp deleted_at
    }
    SCHOOL_CLASS_CHANNEL_MESSAGES {
        bigint id PK
        bigint school_class_channel_id FK
        bigint sender_id FK
        uuid client_uuid UK
        text body
        timestamp edited_at
        timestamp deleted_at
    }
    CLASS_MEMBERSHIP_AUDITS {
        bigint id PK
        bigint school_class_id
        bigint actor_id
        bigint target_id
        string action
        string old_role
        string new_role
    }
```

`class_membership_audits` intentionally keeps historical numeric identities without cascading foreign keys. Media Library uses a polymorphic `model_type` and `model_id`, so its attachment relation is enforced by application logic rather than a database foreign key.

