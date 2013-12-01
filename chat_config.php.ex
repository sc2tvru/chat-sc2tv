<?php
define( 'DRUPAL_SESSION', 'SESS4a29996287c6a61196a9cfc443f0fdb3' );

define( 'CHAT_DB_HOST', '' );
define( 'CHAT_DB_USER', '' );
define( 'CHAT_DB_PASSWORD', '' );
define( 'CHAT_DB_NAME', '' );
define( 'CHAT_DB_CONNECT_TIMEOUT', 5 );

define( 'CHAT_MEMCACHE_HOST', '127.0.0.1' );
define( 'CHAT_MEMCACHE_PORT', 11211 );

define( 'CHAT_TIMEZONE', 'Europe/Moscow' );

// полный путь к директории чата
define( 'CHAT_BASE_DIR', '' );

// нужно 3 дня после регистрации, чтобы писать в чат
// на данный момент задержка выключена
// define( 'CHAT_TIME_ON_SITE_AFTER_REG_NEEDED', 259200 );
// время в секундах, через которое будет повторно проверяться авторизация
define( 'CHAT_USER_AUTHORIZATION_TTL', 259200 );
// количество сообщений на канале
define( 'CHAT_CHANNEL_MSG_LIMIT', 50 );
// количество сообщений, которые видят модераторы
define( 'CHAT_MODERATORS_MSG_LIMIT', 200 );
// максимальный временной интервал для запроса истории для пользователей
define( 'CHAT_HISTORY_MAX_TIME_DIFFERENCE', 86400 );
// максимальный временной интервал для запроса истории для модераторов
define( 'CHAT_HISTORY_MAX_TIME_DIFFERENCE_MODERATOR', 5184000 );
// время в секундах, через которое данные по модераторам будут повторно извлекаться из базы 
define( 'CHAT_MODERATORS_DETAILS_TTL', 86400 );
// максимальная длина логина
define( 'CHAT_MAX_USERNAME_LENGTH', 60 );

// помечать ли сообщения забаненных пользователей как удаленные
// из базы они не удаляются
define( 'CHAT_DELETE_BANNED_USERS_MESSAGE', true );

// сообщения для пользователя
define( 'CHAT_AUTOBAN_REASON_1', 90 );
// ошибки авторизации
define( 'CHAT_COOKIE_NOT_FOUND', 'В чате могут писать только авторизованные пользователи.' );
define( 'CHAT_UID_FOR_SESSION_NOT_FOUND', 'Ошибка авторизации. Проверьте авторизацию на сайте.' );
define( 'CHAT_TOKEN_VERIFICATION_FAILED', 'Ошибка авторизации (токен). Чат будет обновлен. Если это не поможет, проверьте авторизацию на сайте.' );
define( 'CHAT_USER_BANNED_ON_SITE', 'Вы забанены на сайте. Причину узнавайте у модераторов.' );
define( 'CHAT_NEWBIE_USER', 'С момента вашей регистрации прошло недостаточно времени.' );
define( 'CHAT_USER_BANNED_IN_CHAT', 'Вы забанены в чате.' );

// прочие ошибки
define( 'CHAT_USER_MESSAGE_EMPTY', 'Введите сообщение, прежде чем нажимать Enter.' );
define( 'CHAT_USER_MESSAGE_ERROR', 'Ошибка при отправке сообщения.' );
define( 'CHAT_RUNTIME_ERROR', 'Если видите это, сообщите разработчикам, ошибка ' );
define( 'CHAT_HISTORY_CHECK_PARAMS', 'Пожалуйста, проверьте правильность данных запроса. Максимальный временной интервал для запроса истории - 24 часа.' );
define( 'CHAT_USERNAME_TOO_LONG', 'Пожалуйста, проверьте правильность данных запроса. Некорректная длина имени пользователя(ей).' );

// сброс ошибок в log - true
define( 'LOG_ERRORS', true );

/**
 * настройки для автомодерации пользователями
 * влияют на работу скриптов с automoderation_ в имени
 */

// время жизни голоса гражданина в секундах 
define( 'CITIZEN_VOTE_TTL', 300 );
// количество голосов для бана
define( 'CITIZEN_VOTES_NEEDED', 3 );
// время в секундах, через которое будет повторно проверяться статус гражданина
define( 'CITIZEN_STATUS_TTL', 43200 ); //43200 - 12 часов
// время отслеживания повторных банов в днях
define( 'CITIZEN_REPEATED_BAN_TTL', 14 );
// количество жалоб для подсветки бана
// жалоба гражданина идет за 2
define( 'COMPLAINS_NEEDED', 2 );
// время жизни жалоб в секундах
define( 'COMPLAINS_TTL', 43200 );
// длительность банов, которые не влияют на гражданство
define( 'CITIZEN_ALLOWED_BAN_TIME', 600 );
// время жизни количества сообщений пользователя в чате в секундах, сейчас сутки, предполагалась неделя 604800
define( 'CITIZEN_CHAT_MSG_COUNT', 86400 );
// время, в течение которого кэш канала считается актуальным
define( 'CHANNEL_CACHE_ACTUAL_TTL', 1 );

// количество нарушений, доступных гражданам
// максимальный id нарушения
// обязательно надо обновлять при изменении списка нарушений
define( 'CITIZEN_REASONS_COUNT', 14 );

// критерии для получения гражданства

// время в днях после регистрации
define( 'CITIZEN_DAYS_ON_SITE_AFTER_REG', 60 );

// время в днях, за которое не должно быть нарушений в чате и форуме
// просматриваются последние Х дней
define( 'CITIZEN_DAYS_BEFORE_WITHOUT_INFRACTIONS', 14 );

// общее число сообщений на форуме и комментариев в новостях
define( 'CITIZEN_POSTS_COUNT', 70 );

// число сообщений в чате
define( 'CITIZEN_CHAT_POSTS_COUNT', 50 );

// путь к memfs относительно этого конфига
define( 'CHAT_MEMFS_DIR', CHAT_BASE_DIR . '/memfs' );
// путь к memfs истории относительно этого конфига
define( 'CHAT_HISTORY_MEMFS_DIR', CHAT_MEMFS_DIR . '/history' );
// путь к memfs истории автомодерации относительно этого конфига
define( 'CHAT_AUTOMODERATION_HISTORY_MEMFS_DIR', CHAT_MEMFS_DIR . '/automoderation_history' );
// путь к деталям модераторов в memfs относительно этого конфига
define( 'CHAT_MODERATORS_DETAILS', CHAT_MEMFS_DIR . '/moderatorsDetails.js' );
// путь к жалобам на баны в memfs относительно этого конфига
define( 'CHAT_COMPLAINS_FOR_BANS', CHAT_MEMFS_DIR . '/complainsForBans.js' );

// ключи в Memcache
define( 'MODERATORS_DETAILS_MEMCACHE_KEY', 'ChatModeratorsDetails' );
define( 'COMPLAINS_LIST_MEMCACHE_KEY', 'ChatAutoModerationComplains' );

define( 'CHAT_COOKIE_DOMAIN', '.sc2tv.ru' );
define( 'CHAT_COOKIE_TOKEN', 'chat_token' );
define( 'DEBUG_FILE', CHAT_MEMFS_DIR . '/debug_____gh34aw5u5ja9.txt' );
define( 'ERROR_FILE', CHAT_MEMFS_DIR . '/error_____sgsrhh53y55l.txt' );

// Prime Time
define( 'PRIME_TIME_CHANNEL_ID', 666 );
define( 'PRIME_TIME_CHANNEL_TITLE', 'Prime time' );
define( 'PRIME_TIME_STREAMS_AT_ONE_TIME', 1 );
define( 'PRIME_TIME_NAME', 'PRIME-TIME' );
define( 'PRIME_TIME_UID', '-2' );
// ключи рекламы в Memcache
define( 'PRIME_TIME_ADVERT_FROM_STREAMER', 'PrimeTimeAdvertFromStreamer' );
define( 'PRIME_TIME_ADVERT_FROM_USER', 'PrimeTimeAdvertFromUser' );
?>