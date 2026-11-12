ALTER TABLE `maralab`.`chat_memory`
    ADD COLUMN `kind` VARCHAR(16) NOT NULL DEFAULT 'context',
    ADD COLUMN `embedding` LONGTEXT NULL,
    ADD COLUMN `embedding_key` CHAR(64) NULL;
