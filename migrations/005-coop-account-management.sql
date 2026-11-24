CREATE TABLE IF NOT EXISTS coop_registered_accounts (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    AcctCode VARBINARY(128) NOT NULL,
    AcctNo VARBINARY(128) NOT NULL,
    AccountName VARCHAR(255) NULL,
    GenstatAreaID INT NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY unique_coop_code (AcctCode),
    UNIQUE KEY unique_coop_number (AcctNo),
    KEY coop_area_code (GenstatAreaID, AcctCode),
    KEY coop_account_name (AccountName),
    CONSTRAINT coop_registered_area_fk FOREIGN KEY (GenstatAreaID) REFERENCES area_master (AreaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coop_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id VARCHAR(80) NOT NULL,
    action VARCHAR(40) NOT NULL,
    actor VARCHAR(190) NOT NULL,
    period CHAR(6) NOT NULL,
    details JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY event_operation (operation_id, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
