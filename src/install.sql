CREATE TABLE IF NOT EXISTS `[DB_PREFIX]flame_token`
(
    `token`       varchar(50)      NOT NULL DEFAULT '' COMMENT 'token',
    `type`        varchar(15)      NOT NULL DEFAULT '' COMMENT 'token类型',
    `user_id`     int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
    `create_time` bigint(20)                DEFAULT NULL COMMENT '创建时间',
    `expire_time` bigint(20)                DEFAULT NULL COMMENT '过期时间',
    KEY `idx_user_type` (`user_id`, `type`),
    PRIMARY KEY (`token`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_general_ci
    COMMENT ='用户Token表';