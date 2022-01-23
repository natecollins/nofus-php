
CREATE TABLE IF NOT EXISTS table1 (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    val1 VARCHAR(32) NOT NULL DEFAULT '',
    val2 VARCHAR(32),
    e_val ENUM('first','second','third'),
    PRIMARY KEY (id)
)
CHARACTER SET utf8mb4
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS table2 (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    val1 INTEGER,
    val2 DECIMAL(4,1),
    idx VARCHAR(32) NOT NULL,
    PRIMARY KEY (id),
    INDEX(idx)
)
CHARACTER SET utf8mb4
ENGINE = InnoDB;

INSERT INTO table1
    SET id = 1,
        val1 = "alpha",
        val2 = "beta"
    ON DUPLICATE KEY UPDATE id = id;
INSERT INTO table1
    SET id = 2,
        val1 = "gamma",
        val2 = "delta"
    ON DUPLICATE KEY UPDATE id = id;
INSERT INTO table2
    SET id = 1,
        val1 = 10,
        val2 = 11,
        idx = "one"
    ON DUPLICATE KEY UPDATE id = id;
INSERT INTO table2
    SET id = 2,
        val1 = 20,
        val2 = 21,
        idx = "two"
    ON DUPLICATE KEY UPDATE id = id;

