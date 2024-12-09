CREATE TABLE IF NOT EXISTS `__PREFIX__dataimport` (
     `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
     `data_table` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据表',
     `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '管理员',
     `file` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '文件',
     `records` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '记录数',
     `import_success_records` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '导入成功记录数',
     `radio` enum('upload','import','cancel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload' COMMENT '状态:upload=已上传,import=已导入,cancel=已取消',
     `create_time` bigint(16) unsigned DEFAULT NULL COMMENT '创建时间',
     PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据导入记录';