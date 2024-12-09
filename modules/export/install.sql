CREATE TABLE IF NOT EXISTS `__PREFIX__export_example`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '名字',
  `age` int(2) NOT NULL COMMENT '年龄',
  `addr` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '住址',
  `h` int(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '身高',
  `status` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1' COMMENT '性别:0=女,1=男',
  `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '权重',
  `update_time` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '更新时间',
  `create_time` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT=3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '导出测试表';

BEGIN;
INSERT INTO `__PREFIX__export_example` VALUES (1, '张三', 18, '上海市闸北区', 180, '1', 1, 1664255742, 1664255742);
INSERT INTO `__PREFIX__export_example` VALUES (2, '李四', 25, '江苏省苏州市工业园区', 175, '1', 2, 1664255759, 1664255759);
COMMIT;