<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `whistleblowing`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration whistleblowing: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'activity_log' => [
            'create' => 'CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `attribute_changes` json DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'log_name' => '`log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `log_name`',
                'subject_type' => '`subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`',
                'subject_id' => '`subject_id` bigint unsigned DEFAULT NULL AFTER `subject_type`',
                'event' => '`event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `subject_id`',
                'causer_type' => '`causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `event`',
                'causer_id' => '`causer_id` bigint unsigned DEFAULT NULL AFTER `causer_type`',
                'attribute_changes' => '`attribute_changes` json DEFAULT NULL AFTER `causer_id`',
                'properties' => '`properties` json DEFAULT NULL AFTER `attribute_changes`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `properties`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'subject' => 'KEY `subject` (`subject_type`,`subject_id`)',
                'causer' => 'KEY `causer` (`causer_type`,`causer_id`)',
                'activity_log_log_name_index' => 'KEY `activity_log_log_name_index` (`log_name`)',
            ],
            'foreign' => [
            ],
        ],
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'value' => '`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` int NOT NULL AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'owner' => '`owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` int NOT NULL AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'companies' => [
            'create' => 'CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_name_with_logo` tinyint(1) NOT NULL DEFAULT \'0\',
  `brand_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'#1d4ed8\',
  `shared_passcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webmaster_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passcode_rotated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'slug' => '`slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'logo_path' => '`logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `slug`',
                'show_name_with_logo' => '`show_name_with_logo` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `logo_path`',
                'brand_color' => '`brand_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'#1d4ed8\' AFTER `show_name_with_logo`',
                'shared_passcode' => '`shared_passcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `brand_color`',
                'webmaster_email' => '`webmaster_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `shared_passcode`',
                'passcode_rotated_at' => '`passcode_rotated_at` timestamp NULL DEFAULT NULL AFTER `webmaster_email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `passcode_rotated_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'companies_slug_unique' => 'UNIQUE KEY `companies_slug_unique` (`slug`)',
            ],
            'foreign' => [
            ],
        ],
        'company_user' => [
            'create' => 'CREATE TABLE `company_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_user_user_id_foreign` (`user_id`),
  KEY `company_user_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'user_id' => '`user_id` bigint unsigned NOT NULL AFTER `id`',
                'company_id' => '`company_id` bigint unsigned NOT NULL AFTER `user_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `company_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'company_user_user_id_foreign' => 'KEY `company_user_user_id_foreign` (`user_id`)',
                'company_user_company_id_foreign' => 'KEY `company_user_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'company_user_company_id_foreign' => 'CONSTRAINT `company_user_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE',
                'company_user_user_id_foreign' => 'CONSTRAINT `company_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
            ],
        ],
        'corrective_measure_templates' => [
            'create' => 'CREATE TABLE `corrective_measure_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `corrective_measure_templates_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `company_id`',
                'content' => '`content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `title`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `content`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'corrective_measure_templates_company_id_foreign' => 'KEY `corrective_measure_templates_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'corrective_measure_templates_company_id_foreign' => 'CONSTRAINT `corrective_measure_templates_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE',
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'uuid' => '`uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'connection' => '`connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'queue' => '`queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'exception' => '`exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
            ],
            'foreign' => [
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `failed_jobs`',
                'options' => '`options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL AFTER `options`',
                'created_at' => '`created_at` int NOT NULL AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'attempts' => '`attempts` tinyint unsigned NOT NULL AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_reserved_at_available_at_index' => 'KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`)',
            ],
            'foreign' => [
            ],
        ],
        'media' => [
            'create' => 'CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL,
  `manipulations` json NOT NULL,
  `custom_properties` json NOT NULL,
  `generated_conversions` json NOT NULL,
  `responsive_images` json NOT NULL,
  `order_column` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'model_type' => '`model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'model_id' => '`model_id` bigint unsigned NOT NULL AFTER `model_type`',
                'uuid' => '`uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `model_id`',
                'collection_name' => '`collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `collection_name`',
                'file_name' => '`file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'mime_type' => '`mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `file_name`',
                'disk' => '`disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mime_type`',
                'conversions_disk' => '`conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `disk`',
                'size' => '`size` bigint unsigned NOT NULL AFTER `conversions_disk`',
                'manipulations' => '`manipulations` json NOT NULL AFTER `size`',
                'custom_properties' => '`custom_properties` json NOT NULL AFTER `manipulations`',
                'generated_conversions' => '`generated_conversions` json NOT NULL AFTER `custom_properties`',
                'responsive_images' => '`responsive_images` json NOT NULL AFTER `generated_conversions`',
                'order_column' => '`order_column` int unsigned DEFAULT NULL AFTER `responsive_images`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `order_column`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'media_uuid_unique' => 'UNIQUE KEY `media_uuid_unique` (`uuid`)',
                'media_model_type_model_id_index' => 'KEY `media_model_type_model_id_index` (`model_type`,`model_id`)',
                'media_order_column_index' => 'KEY `media_order_column_index` (`order_column`)',
            ],
            'foreign' => [
            ],
        ],
        'messages' => [
            'create' => 'CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_from_reporter` tinyint(1) NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_report_id_foreign` (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'report_id' => '`report_id` bigint unsigned NOT NULL AFTER `id`',
                'body' => '`body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `report_id`',
                'is_from_reporter' => '`is_from_reporter` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `body`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_from_reporter`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'messages_report_id_foreign' => 'KEY `messages_report_id_foreign` (`report_id`)',
            ],
            'foreign' => [
                'messages_report_id_foreign' => 'CONSTRAINT `messages_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE',
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'token' => '`token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'reports' => [
            'create' => 'CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `tracking_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'new\',
  `acknowledgement_due_at` timestamp NULL DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `feedback_due_at` timestamp NULL DEFAULT NULL,
  `acknowledgement_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `feedback_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `meeting_requested_at` timestamp NULL DEFAULT NULL,
  `corrective_measures` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `anonymized_at` timestamp NULL DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reports_tracking_token_unique` (`tracking_token`),
  KEY `reports_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'company_id' => '`company_id` bigint unsigned NOT NULL AFTER `id`',
                'tracking_token' => '`tracking_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `company_id`',
                'status' => '`status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'new\' AFTER `tracking_token`',
                'acknowledgement_due_at' => '`acknowledgement_due_at` timestamp NULL DEFAULT NULL AFTER `status`',
                'acknowledged_at' => '`acknowledged_at` timestamp NULL DEFAULT NULL AFTER `acknowledgement_due_at`',
                'feedback_due_at' => '`feedback_due_at` timestamp NULL DEFAULT NULL AFTER `acknowledged_at`',
                'acknowledgement_reminder_sent_at' => '`acknowledgement_reminder_sent_at` timestamp NULL DEFAULT NULL AFTER `feedback_due_at`',
                'feedback_reminder_sent_at' => '`feedback_reminder_sent_at` timestamp NULL DEFAULT NULL AFTER `acknowledgement_reminder_sent_at`',
                'meeting_requested_at' => '`meeting_requested_at` timestamp NULL DEFAULT NULL AFTER `feedback_reminder_sent_at`',
                'corrective_measures' => '`corrective_measures` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `meeting_requested_at`',
                'anonymized_at' => '`anonymized_at` timestamp NULL DEFAULT NULL AFTER `corrective_measures`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `anonymized_at`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `title`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `description`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'reports_tracking_token_unique' => 'UNIQUE KEY `reports_tracking_token_unique` (`tracking_token`)',
                'reports_company_id_foreign' => 'KEY `reports_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'reports_company_id_foreign' => 'CONSTRAINT `reports_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE',
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'user_agent' => '`user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `ip_address`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_superadmin` tinyint(1) NOT NULL DEFAULT \'0\',
  `app_authentication_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `app_authentication_recovery_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`',
                'password' => '`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email_verified_at`',
                'is_superadmin' => '`is_superadmin` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `password`',
                'app_authentication_secret' => '`app_authentication_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `is_superadmin`',
                'app_authentication_recovery_codes' => '`app_authentication_recovery_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `app_authentication_secret`',
                'remember_token' => '`remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `app_authentication_recovery_codes`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `remember_token`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
            ],
            'foreign' => [
            ],
        ],
        ];
    }
};
