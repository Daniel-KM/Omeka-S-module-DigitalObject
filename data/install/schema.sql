CREATE TABLE `digital_object` (
    `id` INT NOT NULL,
    `data` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    `source` LONGTEXT DEFAULT NULL,
    `media_type` VARCHAR(190) DEFAULT NULL,
    `storage_id` VARCHAR(190) DEFAULT NULL,
    `extension` VARCHAR(190) DEFAULT NULL,
    `sha256` CHAR(64) DEFAULT NULL,
    `size` BIGINT DEFAULT NULL,
    `has_original` TINYINT(1) DEFAULT 0 NOT NULL,
    `has_thumbnails` TINYINT(1) DEFAULT 0 NOT NULL,
    `lang` VARCHAR(190) DEFAULT NULL,
    `alt_text` LONGTEXT DEFAULT NULL,
    UNIQUE INDEX `UNIQ_45F312995CC5DB90` (`storage_id`),
    INDEX `idx_do_sha256` (`sha256`),
    INDEX `idx_do_media_type` (`media_type`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci` ENGINE = `InnoDB`;

ALTER TABLE `digital_object` ADD CONSTRAINT `FK_45F31299BF396750` FOREIGN KEY (`id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
