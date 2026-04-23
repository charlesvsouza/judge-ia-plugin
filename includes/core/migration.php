<?php
if (!defined('ABSPATH')) exit;

function judgeia_save_conversation($question, $answer) {

    global $wpdb;

    $table = $wpdb->prefix . 'judgeia_conversations';

    $user_id = get_current_user_id();
    $session_hash = null;

    if (!$user_id) {
        $session_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    $wpdb->insert(
        $table,
        [
            'user_id'      => $user_id ?: null,
            'session_hash' => $session_hash,
            'question'     => $question,
            'answer'       => $answer,
        ],
        ['%d', '%s', '%s', '%s']
    );
}