-- Schema 57
START TRANSACTION;
    INSERT INTO config (conf_name, conf_value) VALUES ('auth_imap_toggle', '0');
    INSERT INTO config (conf_name, conf_value) VALUES ('auth_imap_mailbox', '');
    UPDATE `config` SET `conf_value` = 57 WHERE `conf_name` = 'schema';
COMMIT;
