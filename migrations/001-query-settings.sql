-- Additive only. Run via tools/setup-query-settings.php, which checks for collisions.
CREATE TABLE genstat_query_definitions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 query_key VARCHAR(80) NOT NULL UNIQUE,
 sql_text MEDIUMTEXT NOT NULL,
 area_column VARCHAR(64) NOT NULL,
 sacode_column VARCHAR(64) NOT NULL DEFAULT '',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE genstat_row_query_mappings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_row_key VARCHAR(180) NOT NULL UNIQUE,
 template_hash CHAR(64) NOT NULL,
 row_label VARCHAR(255) NOT NULL,
 query_definition_id BIGINT UNSIGNED NOT NULL,
 value_column VARCHAR(64) NOT NULL,
 bindings_json TEXT NOT NULL,
 excluded_json TEXT NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY (query_definition_id) REFERENCES genstat_query_definitions(id)
) ENGINE=InnoDB;
CREATE TABLE genstat_query_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_row_key VARCHAR(180) NOT NULL,
 snapshot_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX (template_row_key)
) ENGINE=InnoDB;
