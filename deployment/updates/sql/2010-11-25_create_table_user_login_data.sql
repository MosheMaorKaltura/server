DROP TABLE IF EXISTS `user_login_data`;


CREATE TABLE `user_login_data`
(
	`id` INTEGER  NOT NULL AUTO_INCREMENT,
	`login_email` VARCHAR(100)  NOT NULL,
	`first_name` VARCHAR(40),
	`last_name` VARCHAR(40),
	`sha1_password` VARCHAR(40)  NOT NULL,
	`salt` VARCHAR(32)  NOT NULL,
	`login_blocked_until` DATETIME,
	`is_admin` TINYINT,
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`custom_data` TEXT(255),
	PRIMARY KEY (`id`),
	KEY `login_email_index`(`login_email`)
)Type=MyISAM;