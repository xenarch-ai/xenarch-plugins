CREATE TABLE IF NOT EXISTS `#__xenarch_bot_log` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `signature` varchar(255) NOT NULL,
    `category` varchar(50) NOT NULL DEFAULT '',
    `company` varchar(100) NOT NULL DEFAULT '',
    `first_seen` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `last_seen` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `hit_count` bigint(20) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `signature` (`signature`),
    KEY `category` (`category`),
    KEY `last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__xenarch_cache` (
    `cache_key` varchar(255) NOT NULL,
    `cache_value` mediumtext NOT NULL,
    `expires_at` datetime NOT NULL,
    PRIMARY KEY (`cache_key`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
