<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_judgeia_send_message', 'judgeia_handle_message');
add_action('wp_ajax_nopriv_judgeia_send_message', 'judgeia_handle_message');
add_action('wp_ajax_judgeia_send_feedback', 'judgeia_handle_feedback');
add_action('wp_ajax_nopriv_judgeia_send_feedback', 'judgeia_handle_feedback');

function judgeia_handle_message() {

    check_ajax_referer('judgeia_nonce', 'nonce');

    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

    if (!$message) {
        wp_send_json_error(['message' => 'Mensagem vazia.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'judgeia_conversations';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        wp_send_json_error(['message' => 'Sistema ainda não inicializado corretamente.']);
    }

    $geral = get_option('judgeia_settings_geral');

    $daily_limit_messages = intval($geral['daily_limit'] ?? 20);
    $daily_token_limit    = intval($geral['daily_token_limit'] ?? 0); // 0 = ilimitado

    $user_id = get_current_user_id();
    $session_hash = null;

    if (!$user_id) {
        $session_hash = hash(
            'sha256',
            ($_SERVER['REMOTE_ADDR'] ?? '') .
            ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
    }

    /*
    |------------------------------------------
    | MEMÓRIA (ÚLTIMAS 6 INTERAÇÕES)
    |------------------------------------------
    */

    $history = [];
    $limit_history = 6;

    if ($user_id) {
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question, answer FROM $table
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $limit_history
            ),
            ARRAY_A
        );
    } else {
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question, answer FROM $table
                 WHERE session_hash = %s
                 ORDER BY created_at DESC
                 LIMIT %d",
                $session_hash,
                $limit_history
            ),
            ARRAY_A
        );
    }

    if ($results) {
        $history = array_reverse($results);
    }

    /*
    |------------------------------------------
    | LIMITES DIÁRIOS (MENSAGENS + TOKENS)
    |------------------------------------------
    */

    $today_start = date('Y-m-d 00:00:00');
    $today_end   = date('Y-m-d 23:59:59');

    // ----- LIMITE POR MENSAGENS -----

    if ($daily_limit_messages > 0) {

        if ($user_id) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table
                     WHERE user_id = %d
                     AND created_at BETWEEN %s AND %s",
                    $user_id,
                    $today_start,
                    $today_end
                )
            );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table
                     WHERE session_hash = %s
                     AND created_at BETWEEN %s AND %s",
                    $session_hash,
                    $today_start,
                    $today_end
                )
            );
        }

        if ($count >= $daily_limit_messages) {
            wp_send_json_error([
                'message' => 'Você atingiu o limite diário de requisições.'
            ]);
        }
    }

    // ----- LIMITE POR TOKENS -----

    if ($daily_token_limit > 0) {

        if ($user_id) {
            $tokens_used_today = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(tokens_used) FROM $table
                     WHERE user_id = %d
                     AND created_at BETWEEN %s AND %s",
                    $user_id,
                    $today_start,
                    $today_end
                )
            );
        } else {
            $tokens_used_today = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(tokens_used) FROM $table
                     WHERE session_hash = %s
                     AND created_at BETWEEN %s AND %s",
                    $session_hash,
                    $today_start,
                    $today_end
                )
            );
        }

        $tokens_used_today = intval($tokens_used_today ?? 0);

        if ($tokens_used_today >= $daily_token_limit) {
            wp_send_json_error([
                'message' => 'Você atingiu o limite diário de uso de tokens.'
            ]);
        }
    }

    /*
    |------------------------------------------
    | CHAMA PROVIDER
    |------------------------------------------
    */

    $settings = get_option('judgeia_settings_provedores');
    $provider = $settings['active_provider'] ?? 'gemini';

    if ($provider === 'openai') {
        $result = judgeia_openai_send($message, $history);
    } else {
        $result = judgeia_gemini_send($message, $history);
    }

    if (is_array($result) && isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    if (!$result || empty($result['content'])) {
        wp_send_json_error(['message' => 'Erro ao comunicar com a API. Verifique os logs de erro do WordPress.']);
    }

    $response = $result['content'];
    $tokens   = intval($result['tokens'] ?? 0);

    /*
    |------------------------------------------
    | LIMPEZA OPCIONAL
    |------------------------------------------
    */

    if (function_exists('judgeia_clean_response')) {
        $response = judgeia_clean_response($response);
    }

    /*
    |------------------------------------------
    | SALVA CONVERSA
    |------------------------------------------
    */

    if (function_exists('judgeia_save_conversation')) {
        judgeia_save_conversation($message, $response, $tokens);
    }

    wp_send_json_success([
        'response' => $response
    ]);
}

function judgeia_handle_feedback() {

    check_ajax_referer('judgeia_nonce', 'nonce');

    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    $transcript = isset($_POST['transcript']) ? sanitize_textarea_field(wp_unslash($_POST['transcript'])) : '';
    $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';

    if ($rating < 1 || $rating > 5) {
        wp_send_json_error(['message' => 'Nota inválida.']);
    }

    $geral = get_option('judgeia_settings_geral');
    $recipient = sanitize_email($geral['feedback_email'] ?? '');

    if (!$recipient) {
        $recipient = get_option('admin_email');
    }

    if (!$recipient || !is_email($recipient)) {
        wp_send_json_error(['message' => 'Nenhum e-mail de destino válido foi configurado.']);
    }

    $current_user = wp_get_current_user();
    $user_label = 'Visitante';

    if ($current_user instanceof WP_User && $current_user->exists()) {
        $user_label = $current_user->user_email ?: $current_user->display_name;
    }

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subject = sprintf('[%s] Nova pesquisa de satisfação do chat', $site_name);

    $body = implode("\n", [
        'Nova avaliação recebida do chat Judge IA.',
        '',
        'Nota: ' . $rating . '/5',
        'Usuário: ' . $user_label,
        'Página: ' . ($page_url ?: 'Não informada'),
        '',
        'Comentário:',
        $comment ?: 'Sem comentário.',
        '',
        'Transcrição da conversa:',
        $transcript ?: 'Sem transcrição disponível.',
    ]);

    $sent = wp_mail($recipient, $subject, $body);

    if (!$sent) {
        wp_send_json_error(['message' => 'Falha ao enviar o e-mail da pesquisa.']);
    }

    wp_send_json_success(['message' => 'Pesquisa enviada com sucesso.']);
}