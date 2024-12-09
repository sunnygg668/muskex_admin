<?php

namespace App\common\library;


/**
 * RedisKey 管理类
 */
class RedisKey
{
    //！！！注意使用相同KEY时，查询条件、存储的结构和字段必须一致！！！
    const USER_INFO_UNCHANGE = "user_info_unchange:";//不会改变的字段可以使用
    const TRANSFER_USER_INVITATIONCODE = "transfer_user_invitationcode:";
    const USER_INVITATIONCODE = "user_invitationcode:";
    const DOMAIN_NAME = "domain_name:";
    const COIN_HOME_RECOMMEND = "coin_home_recommend";
    const NOTICE_LIST = "notice_list";
    const NEWS_LIST = "news_list";
    const CONFIG = "config:";
    const CONFIG_GROUP = "config_group:";
    const SYS_CONFIG_ALL = "sys_config_all";
    const COIN_LIST = "coin_list";
    const COIN_MARGIN = "coin_margin:";
    const CAROUSEL = "carousel";
    const COIN = "coin:";
    const CONTRACT = "contract:";
    const COIN_ASSETS_LIST = "coin_assets_list";
    const USER_REQUEST_LIMIT = "user_request_limit:";
    const USER_REQUEST_LIMIT_OVER = "user_request_limit_over:";
    const IP_REQUEST_LIMIT = "ip_request_limit:";
    const IP_REQUEST_LIMIT_OVER = "ip_request_limit_over:";
    const TASK = "task:";
    const STATISTICS_TASK = "statistics_task:";
    const CONTRACT_ORDER_LOCK = "contract_order_lock:";
    const CONTRACT_ORDER_SELL_LOCK = "contract_order_sell_lock:";
    const SMS_LIMIT= "sms_limit:";
    const SEND_SMS_LOCK = "send_sms_lock:";
    const REPORT_STATISTICS_TOTAL = "reportStatisticsTotal";
    const REPORT_TEAM_STATISTICS_TOTAL = "reportTeamStatisticsTotal";
    const LEVEL = "level:";



}