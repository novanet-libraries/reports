CREATE DATABASE IF NOT EXISTS reports_cache
DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE = utf8_unicode_ci;

USE reports_cache;

CREATE TABLE IF NOT EXISTS cache_state (
  param_string varchar(255) NOT NULL,
  last_update  datetime     DEFAULT NULL,
  PRIMARY KEY (param_string)
);

-- And then one table for every cache-able report....
-- Each report must have a foreign key linking its param_string
-- back to the cache_state table.
CREATE TABLE IF NOT EXISTS invalid_sublibraries (
  param_string    varchar(255) NOT NULL,
  Z30_SUB_LIBRARY varchar(6)   DEFAULT NULL,
  C               int(11)      DEFAULT NULL,
  KEY param_string (param_string),
  CONSTRAINT invalid_sublibraries_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS items_per_collection (
  param_string   varchar(255) NOT NULL,
  Z30_COLLECTION varchar(6)   DEFAULT NULL,
  C              int(11)      DEFAULT NULL,
  KEY param_string (param_string),
  CONSTRAINT items_per_collection_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS registrars_list (
  param_string      varchar(255) NOT NULL,
  Z303_HOME_LIBRARY varchar(6)   DEFAULT NULL,
  Z305_EXPIRY_DATE  varchar(10)  DEFAULT NULL,
  Z303_NAME         varchar(64)  DEFAULT NULL,
  AMOUNT            varchar(10)  DEFAULT NULL,
  BARCODE           varchar(100) DEFAULT NULL,
  INST_ID           varchar(100) DEFAULT NULL,
  KEY reg_list_params (param_string),
  CONSTRAINT registrars_list_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS small_old_fines (
  param_string      varchar(255) NOT NULL,
  Z303_REC_KEY      varchar(12)  DEFAULT NULL,
  Z303_NAME         varchar(200) DEFAULT NULL,
  Z303_HOME_LIBRARY varchar(5)   DEFAULT NULL,
  AMOUNT            varchar(5)   DEFAULT NULL,
  BARCODE           varchar(100) DEFAULT NULL,
  INST_ID           varchar(100) DEFAULT NULL,
  KEY param_string (param_string),
  CONSTRAINT small_old_fines_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS spent_ytd_list (
  param_string      varchar(255) NOT NULL,
  BUDGET_NUMBER     varchar(50)  DEFAULT NULL,
  Z76_NAME          varchar(60)  DEFAULT NULL,
  LOCALSUM          varchar(20)  DEFAULT NULL,
  ORDER_NUMBER      varchar(30)  DEFAULT NULL,
  Z68_ORDERING_UNIT varchar(5)   DEFAULT NULL,
  VENDOR_CODE       varchar(20)  DEFAULT NULL,
  Z68_ORDER_TYPE    char(1)      DEFAULT NULL,
  Z68_MATERIAL_TYPE varchar(2)   DEFAULT NULL,
  UNITS_ORDERED     varchar(5)   DEFAULT NULL,
  UNITS_INVOICED    varchar(5)   DEFAULT NULL,
  UNITS_ARRIVED     varchar(20)  DEFAULT NULL,
  OBJECT_CODE       varchar(5)   DEFAULT NULL,
  Z77_P_DATE        char(8)      DEFAULT NULL,
  ADM_NUMBER        char(9)      DEFAULT NULL,
  BIB_NUMBER        char(9)      DEFAULT NULL,
  Z13_AUTHOR        varchar(100) DEFAULT NULL,
  Z13_TITLE         varchar(100) DEFAULT NULL,
  Z13_IMPRINT       varchar(100) DEFAULT NULL,
  Z13_ISBN_ISSN     varchar(100) DEFAULT NULL,
  Z13_CALL_NO       varchar(100) DEFAULT NULL,
  KEY spent_ytd_list_idx (param_string),
  CONSTRAINT spent_ytd_list_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

CREATE TABLE spent_ytd_summary (
  param_string   varchar(255) NOT NULL,
  BUDGET_NUMBER  varchar(50)  DEFAULT NULL,
  BUDGET         varchar(60)  DEFAULT NULL,
  ALLOCATED      varchar(20)  DEFAULT NULL,
  MAX_OVER_SPEND varchar(20)  DEFAULT NULL,
  SPENT          varchar(20)  DEFAULT NULL,
  PERCENT_SPENT  varchar(7)   DEFAULT NULL,
  KEY ytd_summary_params (param_string),
  CONSTRAINT spent_ytd_summary_ibfk_1 FOREIGN KEY (param_string) REFERENCES cache_state (param_string) ON DELETE CASCADE
);

GRANT SELECT,INSERT,UPDATE,DELETE,EXECUTE,LOCK TABLES,CREATE TEMPORARY TABLES
ON reports_cache.*
TO reports@localhost IDENTIFIED BY 'somepassword';
