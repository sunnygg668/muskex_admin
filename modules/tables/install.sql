-- ----------------------------
-- Table structure for __PREFIX__examples_table_tree
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_tree`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `address`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
    `code`        varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮编',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '树形数据'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_tree
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_tree` (id, string, date, address, code, create_time)
VALUES (1, '字符串', '2023-08-07', '详细地址0', '1234', 1691560167),
       (2, '字符串1', '2023-08-08', '详细地址1', '1235', 1691560180),
       (3, '字符串2', '2023-08-09', '详细地址2', '24654', 1691560192);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_summary
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_summary`
(
    `id`          int UNSIGNED    NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `number1`     int             NOT NULL DEFAULT 0 COMMENT '数字1',
    `number2`     int             NOT NULL DEFAULT 0 COMMENT '数字2',
    `float1`      decimal(5, 2)   NOT NULL DEFAULT 0.00 COMMENT '浮点数1',
    `float2`      decimal(5, 2)   NOT NULL DEFAULT 0.00 COMMENT '浮点数2',
    `create_time` bigint UNSIGNED NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '表尾合计行'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_summary
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_summary` (id, number1, number2, float1, float2, create_time)
VALUES (1, 1, 2, 1.10, 2.20, 1691515301),
       (2, 11, 22, 11.11, 22.22, 1691515321),
       (3, 111, 222, 111.11, 222.22, 1691515333);


-- ----------------------------
-- Table structure for __PREFIX__examples_table_status
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_status`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `number`      int                                                           NOT NULL DEFAULT 0 COMMENT '数字',
    `float`       decimal(5, 2)                                                 NOT NULL DEFAULT 0.00 COMMENT '浮点数',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `update_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '修改时间',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '带状态表格'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_status
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_status` (id, string, number, `float`, date, update_time, datetime, create_time)
VALUES (1, '字符串1', 1, 1.10, '2023-08-05', 1691517031, '2023-08-05 00:00:00', 1691517031),
       (2, '字符串2', 2, 2.20, '2023-08-06', 1691517044, '2023-08-06 00:00:00', 1691517044),
       (3, '字符串3', 3, 3.30, '2023-08-07', 1691517056, '2023-08-07 00:00:00', 1691517056),
       (4, '字符串4', 4, 4.40, '2023-08-08', 1691517071, '2023-08-08 00:00:00', 1691517071);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_span
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_span`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `user_id`     int UNSIGNED                                                  NOT NULL DEFAULT 0 COMMENT '远程下拉',
    `city`        varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '城市',
    `address`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
    `code`        varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮编',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '合并行或列'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_span
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_span` (id, date, user_id, city, address, code, create_time)
VALUES (1, '2023-08-09', 1, '重庆', '详细地址', '1234', 1691523966),
       (2, '2023-08-09', 1, '北京', '北京-详细地址-4567', '4567', 1691523985),
       (3, '2023-08-09', 1, '上海', '上海-详细地址-7896', '7896', 1691523998);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_refresh
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_refresh`
(
    `id`     int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '编程式刷新表格'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_refresh
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_refresh` (id, string)
VALUES (1, '我是字符串');

-- ----------------------------
-- Table structure for __PREFIX__examples_table_mheader
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_mheader`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `user_id`     int UNSIGNED                                                  NOT NULL DEFAULT 0 COMMENT '远程下拉',
    `city`        varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '城市',
    `address`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
    `code`        varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮编',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '多级表头示例'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_mheader
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_mheader` (id, date, user_id, city, address, code, create_time)
VALUES (1, '2023-08-09', 1, '重庆', '详细地址1', '1234', 1691519092),
       (2, '2023-08-10', 1, '北京', '详细地址2', '4567', 1691519107),
       (3, '2023-08-11', 1, '上海', '详细地址', '7896', 1691519121);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_method
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_method`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `switch`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '开关:0=关,1=开',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '操作表格的方法'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_method
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_method` (id, string, switch, datetime, create_time)
VALUES (1, '我是字符串1', 1, '2023-08-08 00:00:00', 1691503697),
       (2, '我是字符串2', 1, '2023-12-12 06:06:06', 1691503702),
       (3, '我是字符串3', 0, '2023-08-09 00:00:00', 1691511650);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_header_btn
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_header_btn`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `number`      int                                                           NOT NULL DEFAULT 0 COMMENT '数字',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '自定义表头按钮'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_header_btn
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_header_btn` (id, string, number, create_time)
VALUES (1, '我是字符串1', 1, 1691566170),
       (2, '我是字符串2', 2, 1691566177);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_form_submit
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_form_submit`
(
    `id`     int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '表单提交前数据处理'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_form_submit
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_form_submit` (id, string)
VALUES (1, '我是字符串');

-- ----------------------------
-- Table structure for __PREFIX__examples_table_form_other
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_form_other`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `number`      int                                                           NOT NULL DEFAULT 0 COMMENT '数字',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `image`       varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图片',
    `user_id`     int UNSIGNED                                                  NOT NULL DEFAULT 0 COMMENT '会员ID',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '表单其他示例'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_form_other
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_form_other` (id, string, number, datetime, image, user_id, create_time)
VALUES (1, '我是字符串添加时默认值', 66, '2023-08-08 06:06:06', '/static/images/avatar.png', 1, 1691425946);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_form_edit
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_form_edit`
(
    `id`     int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '数据编辑之前预处理'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_form_edit
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_form_edit` (id, string)
VALUES (1, '我是字符串');

-- ----------------------------
-- Table structure for __PREFIX__examples_table_fixed
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_fixed`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string1`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串1',
    `string2`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串2',
    `string3`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串3',
    `string4`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串4',
    `string5`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串5',
    `update_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '修改时间',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '固定列固定表头'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_fixed
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_fixed` (id, string1, string2, string3, string4, string5, update_time, create_time)
VALUES (1, '字符串1', '字符串2', '字符串3', '字符串4', '字符串5', 1691514209, 1691514209),
       (2, '字符串11', '字符串22', '字符串33', '字符串44', '字符串55', 1691514352, 1691514352),
       (3, '字符串111', '字符串222', '字符串333', '字符串444', '字符串555', 1691514363, 1691514363),
       (4, '字符串1111', '字符串2222', '字符串3333', '字符串4444', '字符串5555', 1691514382, 1691514382),
       (5, '字符串11111', '字符串22222', '字符串33333', '字符串44444', '字符串55555', 1691514454, 1691514454),
       (6, '字符串a', '字符串b', '字符串c', '字符串d', '字符串e', 1691514470, 1691514470),
       (7, '字符串f', '字符串g', '字符串h', '字符串i', '字符串j', 1691514504, 1691514504),
       (8, '字符串k', '字符串l', '字符串m', '字符串n', '字符串o', 1691514518, 1691514518),
       (9, '字符串p', '字符串q', '字符串r', '字符串s', '字符串t', 1691514532, 1691514532),
       (10, '字符串u', '字符串v', '字符串w', '字符串x', '字符串y', 1691514546, 1691514546),
       (11, '字符串1', '字符串2', '字符串3', '字符串4', '字符串5', 1691514556, 1691514556),
       (12, '字符串6', '字符串7', '字符串8', '字符串9', '字符串10', 1691514566, 1691514566),
       (13, '字符串11', '字符串12', '字符串13', '字符串14', '字符串15', 1691514601, 1691514601),
       (14, '字符串16', '字符串17', '字符串18', '字符串19', '字符串20', 1691514611, 1691514611),
       (15, '字符串21', '字符串22', '字符串23', '字符串24', '字符串25', 1691514621, 1691514621),
       (16, '字符串26', '字符串27', '字符串28', '字符串29', '字符串30', 1691514630, 1691514630),
       (17, '字符串31', '字符串32', '字符串33', '字符串34', '字符串35', 1691514640, 1691514640),
       (18, '字符串36', '字符串37', '字符串38', '字符串39', '字符串40', 1691514656, 1691514656),
       (19, '字符串41', '字符串42', '字符串43', '字符串44', '字符串45', 1691514666, 1691514666),
       (20, '字符串46', '字符串47', '字符串48', '字符串49', '字符串50', 1691514681, 1691514681),
       (21, '字符串51', '字符串52', '字符串53', '字符串54', '字符串55', 1691514681, 1691514681),
       (22, '字符串56', '字符串57', '字符串58', '字符串59', '字符串60', 1691514681, 1691514681),
       (23, '字符串61', '字符串62', '字符串63', '字符串64', '字符串65', 1691514681, 1691514681),
       (24, '字符串66', '字符串67', '字符串68', '字符串69', '字符串70', 1691514681, 1691514681),
       (25, '字符串71', '字符串72', '字符串73', '字符串74', '字符串75', 1691514681, 1691514681),
       (26, '字符串76', '字符串77', '字符串78', '字符串79', '字符串80', 1691514681, 1691514681);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_expand
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_expand`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `user_id`     int UNSIGNED                                                  NOT NULL DEFAULT 0 COMMENT '会员ID',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `address`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
    `code`        varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮编',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '展开行'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_expand
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_expand` (id, user_id, date, address, code, create_time)
VALUES (1, 1, '2023-08-09', '详细地址', '1234', 1691559616),
       (2, 1, '2023-08-10', '详细地址2', '4567', 1691559949);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_event2
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_event2`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `switch`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '开关:0=关,1=开',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '表格事件监听2'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_event2
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_event2` (id, string, switch, datetime, create_time)
VALUES (1, '我是字符串', 1, '2023-08-08 00:00:00', 1691500045);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_event
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_event`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `switch`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '开关:0=关,1=开',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '表格事件监听'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_event
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_event` (id, string, switch, datetime, create_time)
VALUES (1, '我是字符串', 1, '2023-08-08 00:00:00', 1691481127),
       (2, '我是字符串2', 1, '2023-08-09 00:00:00', 1691499732);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_dialog2
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_dialog2`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `switch`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '开关:0=关,1=开',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '详情按钮和弹窗2'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_dialog2
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_dialog2` (id, string, switch, datetime, create_time)
VALUES (1, '我是字符串', 1, '2023-08-08 00:00:00', 1691474476),
       (2, '我是字符串2', 1, '2023-08-09 00:00:00', 1691475355);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_dialog
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_dialog`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `switch`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '开关:0=关,1=开',
    `datetime`    datetime                                                      NULL     DEFAULT NULL COMMENT '时间日期',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '详情按钮和弹窗'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_dialog
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_dialog` (id, string, switch, datetime, create_time)
VALUES (1, '我是字符串1', 1, '2023-08-08 00:00:00', 1691434595),
       (2, '我是字符串2', 1, '2023-08-09 00:00:00', 1691478034);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_cell_slot
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_cell_slot`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `date`        date                                                          NULL     DEFAULT NULL COMMENT '日期',
    `address`     varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
    `code`        varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮编',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '单元格slot渲染'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_cell_slot
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_cell_slot` (id, string, date, address, code, create_time)
VALUES (1, '我是字符串', '2023-08-09', '我是详细地址1', '12346', 1691561381),
       (2, '我是字符串2', '2023-08-09', '我是详细地址2', '4556', 1691561394),
       (3, '我是字符串3', '2023-08-09', '我是详细地址3', '4567', 1691562855);

-- ----------------------------
-- Table structure for __PREFIX__examples_table_cell_pre
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__examples_table_cell_pre`
(
    `id`          int UNSIGNED                                                  NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `string`      varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字符串',
    `url`         varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接',
    `image`       varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图片',
    `icon`        varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '图标选择',
    `color`       varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '颜色选择器',
    `status`      tinyint UNSIGNED                                              NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
    `weigh`       int                                                           NULL     DEFAULT 0 COMMENT '权重',
    `create_time` bigint UNSIGNED                                               NULL     DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT = '预设渲染方案'
  ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of __PREFIX__examples_table_cell_pre
-- ----------------------------
INSERT INTO `__PREFIX__examples_table_cell_pre` (id, string, url, image, icon, color, status, weigh, create_time)
VALUES (1, '我是字符串', 'https://www.aliyun.com', '/static/images/avatar.png', 'el-icon-ColdDrink', '#C60000', 1, 1, 1691563283);
