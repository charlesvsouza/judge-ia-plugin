<?php
if (!defined('ABSPATH')) exit;

function judgeia_get_conversations_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'judgeia_conversations';
}

function judgeia_install_or_update_database() {

    global $wpdb;

    $table = judgeia_get_conversations_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        session_hash VARCHAR(64) NULL,
        question LONGTEXT NOT NULL,
        answer LONGTEXT NOT NULL,
        tokens_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function judgeia_save_conversation($question, $answer, $tokens = 0) {

    global $wpdb;

    $table = judgeia_get_conversations_table_name();

    $user_id = get_current_user_id();
    $session_hash = null;

    if (!$user_id) {
        $session_hash = hash('sha256',
            ($_SERVER['REMOTE_ADDR'] ?? '') .
            ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
    }

    $wpdb->insert(
        $table,
        [
            'user_id'      => $user_id ?: null,
            'session_hash' => $session_hash,
            'question'     => $question,
            'answer'       => $answer,
            'tokens_used'  => intval($tokens),
        ],
        ['%d', '%s', '%s', '%s', '%d']
    );
}