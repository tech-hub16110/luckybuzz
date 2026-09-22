<?php

declare(strict_types=1);

/**
 * Per-dialect DDL.
 *
 * Two things differ enough to warrant a separate file rather than sprinkling
 * conditionals through Db: autoincrement syntax, and how each engine expresses
 * a case-insensitive unique username. Everything else is deliberately the same
 * shape so a bug in one dialect is visible in the other.
 *
 * Timestamps are stored as ISO-8601 strings in both engines on purpose. The
 * game compares them lexicographically (which is order-correct for a fixed UTC
 * offset), and keeping them as strings avoids MySQL's DATETIME silently
 * discarding the offset. The trade-off is documented in the README.
 */
final class Schema
{
    public const TABLES = [
        'users',
        'draws',
        'tickets',
        'ledger',
        'wins',
        'box_opens',
        'meta',
        'lottery_imports',
        'lottery_results',
        'lottery_import_logs',
    ];

    /** @return list<string> one statement each */
    public static function statements(): array
    {
        return Config::isMysql() ? self::mysql() : self::sqlite();
    }

    /** @return list<string> */
    public static function sqlite(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS meta (
                meta_key   TEXT PRIMARY KEY,
                meta_value TEXT NOT NULL
            )',

            'CREATE TABLE IF NOT EXISTS users (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                username       TEXT NOT NULL UNIQUE COLLATE NOCASE,
                pass_hash      TEXT NOT NULL,
                secret         TEXT NOT NULL,
                created_at     TEXT NOT NULL,
                last_bonus_day TEXT,
                streak         INTEGER NOT NULL DEFAULT 0
            )',

            'CREATE TABLE IF NOT EXISTS draws (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                day         TEXT NOT NULL,
                tier        TEXT NOT NULL,
                draw_at     TEXT NOT NULL,
                commit_hash TEXT NOT NULL,
                nonce       TEXT,
                result      TEXT,
                settled_at  TEXT,
                rollover_in INTEGER NOT NULL,
                carry_out   INTEGER,
                UNIQUE (day, tier)
            )',

            'CREATE INDEX IF NOT EXISTS draws_tier_open
                ON draws (tier, result, draw_at)',

            'CREATE TABLE IF NOT EXISTS tickets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                draw_id    INTEGER NOT NULL REFERENCES draws(id) ON DELETE CASCADE,
                number     TEXT NOT NULL,
                cost       INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )',

            'CREATE INDEX IF NOT EXISTS tickets_draw_user
                ON tickets (draw_id, user_id)',

            'CREATE TABLE IF NOT EXISTS ledger (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                delta      INTEGER NOT NULL,
                reason     TEXT NOT NULL,
                ref_table  TEXT,
                ref_id     INTEGER,
                created_at TEXT NOT NULL
            )',

            'CREATE INDEX IF NOT EXISTS ledger_user ON ledger (user_id, id)',
            'CREATE INDEX IF NOT EXISTS ledger_user_reason ON ledger (user_id, reason)',

            'CREATE TABLE IF NOT EXISTS wins (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                draw_id    INTEGER NOT NULL REFERENCES draws(id) ON DELETE CASCADE,
                ticket_id  INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                match_kind TEXT NOT NULL,
                amount     INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (ticket_id)
            )',

            'CREATE INDEX IF NOT EXISTS wins_draw ON wins (draw_id, match_kind)',

            // Only the fact of opening is stored — the coins column is an audit
            // copy of a value that deriveBounded would reproduce from the nonce.
            'CREATE TABLE IF NOT EXISTS box_opens (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                draw_id   INTEGER NOT NULL REFERENCES draws(id) ON DELETE CASCADE,
                coins     INTEGER NOT NULL,
                opened_at TEXT NOT NULL,
                UNIQUE (user_id, draw_id)
            )',

            // External Lottery Sambad Import Tracking
            'CREATE TABLE IF NOT EXISTS lottery_imports (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                draw_date        TEXT NOT NULL,
                draw_time        TEXT NOT NULL,
                source           TEXT NOT NULL,
                status           TEXT NOT NULL,
                attempt_count    INTEGER NOT NULL DEFAULT 0,
                last_attempt_at  TEXT,
                imported_at      TEXT,
                source_reference TEXT,
                error_message    TEXT,
                created_at       TEXT NOT NULL,
                updated_at       TEXT NOT NULL,
                UNIQUE (draw_date, draw_time)
            )',

            'CREATE INDEX IF NOT EXISTS lottery_imports_date_time
                ON lottery_imports (draw_date, draw_time)',

            // External Lottery Sambad Normalized Result Items
            'CREATE TABLE IF NOT EXISTS lottery_results (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                import_id      INTEGER NOT NULL REFERENCES lottery_imports(id) ON DELETE CASCADE,
                prize_category TEXT NOT NULL,
                display_result TEXT NOT NULL,
                series         TEXT,
                number         TEXT NOT NULL,
                sort_order     INTEGER NOT NULL DEFAULT 0,
                source_type    TEXT NOT NULL,
                confidence     REAL,
                raw_extracted  TEXT,
                created_at     TEXT NOT NULL
            )',

            'CREATE INDEX IF NOT EXISTS lottery_results_import_prize
                ON lottery_results (import_id, prize_category, sort_order)',
            'CREATE INDEX IF NOT EXISTS lottery_results_number
                ON lottery_results (number)',

            // Structured Import Audit Logs
            'CREATE TABLE IF NOT EXISTS lottery_import_logs (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                draw_date           TEXT NOT NULL,
                draw_time           TEXT NOT NULL,
                attempt             INTEGER NOT NULL,
                source              TEXT NOT NULL,
                http_status         INTEGER,
                api_success         INTEGER NOT NULL DEFAULT 0,
                normalization_count INTEGER NOT NULL DEFAULT 0,
                validation_status   TEXT NOT NULL,
                ai_used             INTEGER NOT NULL DEFAULT 0,
                ai_model            TEXT,
                duration_ms         INTEGER NOT NULL DEFAULT 0,
                error               TEXT,
                final_status        TEXT NOT NULL,
                created_at          TEXT NOT NULL
            )',

            'CREATE INDEX IF NOT EXISTS lottery_import_logs_date_time
                ON lottery_import_logs (draw_date, draw_time, id)',
        ];
    }

    /** @return list<string> */
    public static function mysql(): array
    {
        // utf8mb4_unicode_ci is case-insensitive, which is what makes the unique
        // username rule behave like SQLite's COLLATE NOCASE.
        $tail = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS meta (
                meta_key   VARCHAR(32) NOT NULL PRIMARY KEY,
                meta_value TEXT NOT NULL
            ) $tail",

            "CREATE TABLE IF NOT EXISTS users (
                id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username       VARCHAR(20) NOT NULL UNIQUE,
                pass_hash      VARCHAR(255) NOT NULL,
                secret         CHAR(32) NOT NULL,
                created_at     CHAR(25) NOT NULL,
                last_bonus_day CHAR(10),
                streak         INT NOT NULL DEFAULT 0
            ) $tail",

            "CREATE TABLE IF NOT EXISTS draws (
                id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                day         CHAR(10) NOT NULL,
                tier        VARCHAR(16) NOT NULL,
                draw_at     CHAR(25) NOT NULL,
                commit_hash CHAR(64) NOT NULL,
                nonce       CHAR(32),
                result      CHAR(4),
                settled_at  CHAR(25),
                rollover_in INT NOT NULL,
                carry_out   INT,
                UNIQUE KEY draws_day_tier (day, tier),
                KEY draws_tier_open (tier, result, draw_at)
            ) $tail",

            "CREATE TABLE IF NOT EXISTS tickets (
                id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                draw_id    INT NOT NULL,
                number     CHAR(4) NOT NULL,
                cost       INT NOT NULL,
                created_at CHAR(25) NOT NULL,
                KEY tickets_draw_user (draw_id, user_id),
                CONSTRAINT fk_tickets_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_tickets_draw FOREIGN KEY (draw_id)
                    REFERENCES draws (id) ON DELETE CASCADE
            ) $tail",

            "CREATE TABLE IF NOT EXISTS ledger (
                id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                delta      INT NOT NULL,
                reason     VARCHAR(24) NOT NULL,
                ref_table  VARCHAR(20),
                ref_id     INT,
                created_at CHAR(25) NOT NULL,
                KEY ledger_user (user_id, id),
                KEY ledger_user_reason (user_id, reason),
                CONSTRAINT fk_ledger_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE
            ) $tail",

            "CREATE TABLE IF NOT EXISTS wins (
                id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                draw_id    INT NOT NULL,
                ticket_id  INT NOT NULL,
                user_id    INT NOT NULL,
                match_kind VARCHAR(12) NOT NULL,
                amount     INT NOT NULL,
                created_at CHAR(25) NOT NULL,
                UNIQUE KEY wins_ticket (ticket_id),
                KEY wins_draw (draw_id, match_kind),
                CONSTRAINT fk_wins_draw FOREIGN KEY (draw_id)
                    REFERENCES draws (id) ON DELETE CASCADE,
                CONSTRAINT fk_wins_ticket FOREIGN KEY (ticket_id)
                    REFERENCES tickets (id) ON DELETE CASCADE,
                CONSTRAINT fk_wins_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE
            ) $tail",

            "CREATE TABLE IF NOT EXISTS box_opens (
                id        INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id   INT NOT NULL,
                draw_id   INT NOT NULL,
                coins     INT NOT NULL,
                opened_at CHAR(25) NOT NULL,
                UNIQUE KEY box_user_draw (user_id, draw_id),
                CONSTRAINT fk_box_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_box_draw FOREIGN KEY (draw_id)
                    REFERENCES draws (id) ON DELETE CASCADE
            ) $tail",

            // External Lottery Sambad Import Tracking
            "CREATE TABLE IF NOT EXISTS lottery_imports (
                id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                draw_date        CHAR(10) NOT NULL,
                draw_time        VARCHAR(10) NOT NULL,
                source           VARCHAR(20) NOT NULL,
                status           VARCHAR(20) NOT NULL,
                attempt_count    INT NOT NULL DEFAULT 0,
                last_attempt_at  CHAR(25),
                imported_at      CHAR(25),
                source_reference VARCHAR(255),
                error_message    TEXT,
                created_at       CHAR(25) NOT NULL,
                updated_at       CHAR(25) NOT NULL,
                UNIQUE KEY lottery_imports_date_time (draw_date, draw_time),
                KEY idx_lottery_imports_status (status)
            ) $tail",

            // External Lottery Sambad Normalized Result Items
            "CREATE TABLE IF NOT EXISTS lottery_results (
                id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                import_id      INT NOT NULL,
                prize_category VARCHAR(10) NOT NULL,
                display_result VARCHAR(32) NOT NULL,
                series         VARCHAR(16),
                number         VARCHAR(16) NOT NULL,
                sort_order     INT NOT NULL DEFAULT 0,
                source_type    VARCHAR(20) NOT NULL,
                confidence     DECIMAL(4,3),
                raw_extracted  VARCHAR(64),
                created_at     CHAR(25) NOT NULL,
                KEY idx_lottery_results_import (import_id, prize_category, sort_order),
                KEY idx_lottery_results_number (number),
                CONSTRAINT fk_lottery_results_import FOREIGN KEY (import_id)
                    REFERENCES lottery_imports (id) ON DELETE CASCADE
            ) $tail",

            // Structured Import Audit Logs
            "CREATE TABLE IF NOT EXISTS lottery_import_logs (
                id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                draw_date           CHAR(10) NOT NULL,
                draw_time           VARCHAR(10) NOT NULL,
                attempt             INT NOT NULL,
                source              VARCHAR(20) NOT NULL,
                http_status         INT,
                api_success         TINYINT NOT NULL DEFAULT 0,
                normalization_count INT NOT NULL DEFAULT 0,
                validation_status   VARCHAR(30) NOT NULL,
                ai_used             TINYINT NOT NULL DEFAULT 0,
                ai_model            VARCHAR(64),
                duration_ms         INT NOT NULL DEFAULT 0,
                error               TEXT,
                final_status        VARCHAR(30) NOT NULL,
                created_at          CHAR(25) NOT NULL,
                KEY idx_lottery_logs_date_time (draw_date, draw_time, id)
            ) $tail",
        ];
    }

    /** @return list<string> statements that undo everything, for the test wipe */
    public static function dropAll(): array
    {
        // Children first: the foreign keys block dropping parents.
        return array_map(
            static fn (string $table): string => 'DROP TABLE IF EXISTS ' . $table,
            [
                'lottery_results',
                'lottery_import_logs',
                'lottery_imports',
                'wins',
                'tickets',
                'ledger',
                'box_opens',
                'draws',
                'users',
                'meta',
            ],
        );
    }
}
