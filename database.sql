
CREATE TABLE users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL,
    email         VARCHAR(255)    NOT NULL,
    bio           VARCHAR(1000)       NULL DEFAULT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    admin         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    private       TINYINT(1)      NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email)
);

CREATE TABLE spots (
    id          INT UNSIGNED                                                                    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED                                                                    NOT NULL,
    name        VARCHAR(100)                                                                    NOT NULL,
    description TEXT                                                                            NOT NULL DEFAULT '',
    latitude    DECIMAL(10,7)                                                                   NOT NULL,
    longitude   DECIMAL(10,7)                                                                   NOT NULL,
    spot_type          ENUM('Bando','Feld','Gebirge','Park','Verein','Wasser','Sonstige')       NOT NULL,
    difficulty         ENUM('Anfänger','Mittel','Fortgeschritten','Profi')                      NOT NULL,
    parking_info       VARCHAR(500)                                                             NOT NULL DEFAULT 'Unbekannt',
    parking_updated_by INT UNSIGNED                                                                 NULL DEFAULT NULL,
    parking_updated_at DATETIME                                                                     NULL DEFAULT NULL,                                                         NOT NULL DEFAULT 0,
    created_at         DATETIME                                                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_spots_user_id              (user_id),
    KEY idx_spots_parking_updated_by   (parking_updated_by),

    CONSTRAINT fk_spots_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_spots_parking_updated_by
        FOREIGN KEY (parking_updated_by) REFERENCES users (id)
        ON DELETE SET NULL
);
CREATE TABLE comments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    spot_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_comments_spot_id (spot_id),
    KEY idx_comments_user_id (user_id),

    CONSTRAINT fk_comments_spot
        FOREIGN KEY (spot_id) REFERENCES spots (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE ratings (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    spot_id    INT UNSIGNED    NOT NULL,
    user_id    INT UNSIGNED    NOT NULL,
    stars      TINYINT UNSIGNED NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_ratings_spot_user (spot_id, user_id),
    KEY idx_ratings_user_id (user_id),

    CONSTRAINT fk_ratings_spot
        FOREIGN KEY (spot_id) REFERENCES spots (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ratings_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE spot_images (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    spot_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    filename   VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_spot_images_spot_id (spot_id),
    KEY idx_spot_images_user_id (user_id),

    CONSTRAINT fk_spot_images_spot
        FOREIGN KEY (spot_id) REFERENCES spots (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_spot_images_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE remember_tokens (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    selector       VARCHAR(64)  NOT NULL,
    validator_hash VARCHAR(64)  NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    expires_at     DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_tokens_selector (selector),
    KEY idx_remember_tokens_user_id (user_id),

    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE password_reset_tokens (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    selector       VARCHAR(64)  NOT NULL,
    validator_hash VARCHAR(64)  NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    expires_at     DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_tokens_selector (selector),
    KEY idx_password_reset_tokens_user_id (user_id),

    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE suggestions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_suggestions_user_id (user_id),

    CONSTRAINT fk_suggestions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE suggestion_votes (
    suggestion_id INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,

    PRIMARY KEY (suggestion_id, user_id),

    CONSTRAINT fk_suggestion_votes_suggestion
        FOREIGN KEY (suggestion_id) REFERENCES suggestions (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_suggestion_votes_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
CREATE TABLE contact_requests (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED     NULL DEFAULT NULL,
    email      VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_contact_requests_user_id (user_id),

    CONSTRAINT fk_contact_requests_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
);
CREATE TABLE audit_logs (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED      NULL DEFAULT NULL,
    action     VARCHAR(50)   NOT NULL,
    ip_address VARCHAR(45)   NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action  (action),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
);
CREATE TABLE updates (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_updates_user_id (user_id),

    CONSTRAINT fk_updates_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Spot-Meldungen
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS spot_reports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    spot_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    report_type ENUM('Kommentar','Foto','Spot-Info','Spot-Allgemein') NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_spot_reports_spot_id (spot_id),
    KEY idx_spot_reports_user_id (user_id),

    CONSTRAINT fk_spot_reports_spot
        FOREIGN KEY (spot_id) REFERENCES spots (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_spot_reports_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Benutzer-Benachrichtigungen (z. B. neue Kommentare zu Vorschlägen)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_notifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    type         VARCHAR(64)  NOT NULL,
    reference_id INT UNSIGNED          DEFAULT NULL,
    read_at      DATETIME              DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_user_notifications_user_id (user_id),

    CONSTRAINT fk_user_notifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Konversationen (1-zu-1 Nachrichten)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user1_id         INT UNSIGNED NOT NULL,
    user2_id         INT UNSIGNED NOT NULL,
    last_message_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_by_user1 DATETIME              DEFAULT NULL,
    deleted_by_user2 DATETIME              DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_conversations_pair (user1_id, user2_id),
    KEY idx_conversations_user2 (user2_id),
    KEY idx_conversations_last_message (last_message_at),

    CONSTRAINT fk_conversations_user1
        FOREIGN KEY (user1_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conversations_user2
        FOREIGN KEY (user2_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Nachrichten
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    body            TEXT         NOT NULL,
    read_at         DATETIME              DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_messages_conversation (conversation_id, created_at),
    KEY idx_messages_sender (sender_id),

    CONSTRAINT fk_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE spot_favorites (
    user_id    INT UNSIGNED NOT NULL,
    spot_id    INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, spot_id),
    CONSTRAINT fk_favorites_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_spot
        FOREIGN KEY (spot_id) REFERENCES spots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
