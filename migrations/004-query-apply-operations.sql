CREATE TABLE IF NOT EXISTS genstat_query_apply_operations (
 sequence_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
 operation_id CHAR(48) NOT NULL PRIMARY KEY,
 owner_hash CHAR(64) NOT NULL,
 template_row_key VARCHAR(180) NOT NULL,
 source_hash CHAR(64) NOT NULL,
 draft_revision INT UNSIGNED NOT NULL,
 input_hash CHAR(64) NOT NULL,
 output_hash CHAR(64) NOT NULL,
 baseline_hash CHAR(64) NOT NULL,
 config_hash CHAR(64) NOT NULL,
 record_hash CHAR(64) NOT NULL,
 area_hash CHAR(64) NOT NULL,
 response_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX owner_source (owner_hash, source_hash, created_at)
) ENGINE=InnoDB;
