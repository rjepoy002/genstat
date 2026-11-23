CREATE TABLE coop_enrollment_state (
 id TINYINT UNSIGNED PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO coop_enrollment_state(id) VALUES (1);
CREATE TABLE coop_account_lock (
 account_no VARBINARY(128) PRIMARY KEY
) ENGINE=InnoDB;
CREATE TABLE coop_consumption_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 area_key VARBINARY(180) NOT NULL, acct_code VARBINARY(128) NOT NULL,
 canonical_acct_no VARBINARY(128) NOT NULL,
 effective_from CHAR(6) NOT NULL, effective_to CHAR(6) NULL,
 status VARCHAR(16) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_by VARCHAR(190) NOT NULL, updated_by VARCHAR(190) NOT NULL,
 UNIQUE KEY account_start (canonical_acct_no,effective_from),
 KEY effective_period (effective_from,effective_to),
 CHECK (effective_to IS NULL OR effective_to > effective_from)
) ENGINE=InnoDB;
CREATE TABLE coop_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 operation_id VARCHAR(80) NOT NULL, action VARCHAR(40) NOT NULL,
 actor VARCHAR(190) NOT NULL, period CHAR(6) NOT NULL,
 details JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY event_operation (operation_id,action)
) ENGINE=InnoDB;
CREATE TABLE coop_runs (
 id CHAR(32) PRIMARY KEY, actor VARCHAR(190) NOT NULL, period CHAR(6) NOT NULL,
 template_hash CHAR(64) NOT NULL, enrollment_hash CHAR(64) NOT NULL,
 draft_revision BIGINT UNSIGNED NOT NULL, working_hash CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL, account_count INT UNSIGNED NOT NULL,
 result_json JSON NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY history (period,created_at)
) ENGINE=InnoDB;
CREATE TABLE coop_applications (
 run_id CHAR(32) PRIMARY KEY, actor VARCHAR(190) NOT NULL,
 draft_revision BIGINT UNSIGNED NOT NULL, result_hash CHAR(64) NOT NULL,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (run_id) REFERENCES coop_runs(id)
) ENGINE=InnoDB;
