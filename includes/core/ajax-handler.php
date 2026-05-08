<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_judgeia_send_message', 'judgeia_handle_message');
add_action('wp_ajax_nopriv_judgeia_send_message', 'judgeia_handle_message');
add_action('wp_ajax_judgeia_send_feedback', 'judgeia_handle_feedback');
add_action('wp_ajax_nopriv_judgeia_send_feedback', 'judgeia_handle_feedback');

function judgeia_provider_has_api_key($provider, $settings) {
    if ($provider === 'openai') {
        return trim((string)($settings['openai_api_key'] ?? '')) !== '';
    }

    return trim((string)($settings['gemini_api_key'] ?? '')) !== '';
}

function judgeia_send_via_provider($provider, $message, $history) {
    if ($provider === 'openai') {
        return judgeia_openai_send($message, $history);
    }

    return judgeia_gemini_send($message, $history);
}

function judgeia_result_has_content($result) {
    if (!is_array($result)) {
        return false;
    }

    if (isset($result['error'])) {
        return false;
    }

    if (!isset($result['content'])) {
        return false;
    }

    return trim((string)$result['content']) !== '';
}

function judgeia_normalize_provider_error_message($message) {
    $text = trim((string)$message);
    $text = preg_replace('/^(gemini|openai)_rate_limited:\s*/i', '', $text);
    return $text;
}

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
    $provider = (($settings['active_provider'] ?? 'gemini') === 'openai') ? 'openai' : 'gemini';
    $fallback_provider = ($provider === 'openai') ? 'gemini' : 'openai';

    $attempt_order = [$provider];
    if (judgeia_provider_has_api_key($fallback_provider, $settings)) {
        $attempt_order[] = $fallback_provider;
    }

    $attempt_errors = [];
    $result = false;

    foreach ($attempt_order as $attempt_provider) {
        $current_result = judgeia_send_via_provider($attempt_provider, $message, $history);

        if (judgeia_result_has_content($current_result)) {
            $result = $current_result;
            break;
        }

        $result = $current_result;

        $current_error = '';
        if (is_array($current_result) && isset($current_result['error'])) {
            $current_error = judgeia_normalize_provider_error_message($current_result['error']);
        } elseif ($current_result === false) {
            $current_error = 'Retorno inválido do provedor.';
        } else {
            $current_error = 'O provedor não retornou conteúdo válido.';
        }

        $attempt_errors[$attempt_provider] = $current_error;

        if ($attempt_provider === $provider && count($attempt_order) > 1) {
            error_log(sprintf(
                'Judge IA: fallback automático %s -> %s acionado no chat.',
                strtoupper($provider),
                strtoupper($fallback_provider)
            ));
        }
    }

    if (!judgeia_result_has_content($result)) {
        $error_parts = [];
        foreach ($attempt_order as $attempt_provider) {
            $provider_error = trim((string)($attempt_errors[$attempt_provider] ?? ''));
            if ($provider_error !== '') {
                $error_parts[] = strtoupper($attempt_provider) . ': ' . $provider_error;
            }
        }

        if (!empty($error_parts)) {
            wp_send_json_error(['message' => implode(' | ', $error_parts)]);
        }

        wp_send_json_error(['message' => 'Erro ao comunicar com a API. Verifique as chaves dos provedores e os logs de erro do WordPress.']);
    }

    $response = $result['content'];
    $original_response = $response;
    $tokens   = intval($result['tokens'] ?? 0);

    /*
    |------------------------------------------
    | LIMPEZA OPCIONAL
    |------------------------------------------
    */

    if (function_exists('judgeia_clean_response')) {
        $response = judgeia_clean_response($response);

        // Evita retorno em branco quando a limpeza remove mais do que deveria.
        if ($response === '' && trim((string)$original_response) !== '') {
            $response = $original_response;
        }
    }

    if (trim((string)$response) === '') {
        wp_send_json_error(['message' => 'A IA retornou uma resposta vazia. Tente novamente em instantes.']);
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

    // Campos da pesquisa (5 perguntas Likert 1-5)
    $clarity      = isset($_POST['clarity']) ? intval($_POST['clarity']) : 0;
    $ease_of_use  = isset($_POST['ease_of_use']) ? intval($_POST['ease_of_use']) : 0;
    $utility      = isset($_POST['utility']) ? intval($_POST['utility']) : 0;
    $trust        = isset($_POST['trust']) ? intval($_POST['trust']) : 0;
    $satisfaction = isset($_POST['satisfaction']) ? intval($_POST['satisfaction']) : 0;

    $comment    = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    $transcript = isset($_POST['transcript']) ? sanitize_textarea_field(wp_unslash($_POST['transcript'])) : '';
    $page_url   = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';

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
    $subject = sprintf('[%s] Pesquisa de Satisfação Detalhada - Judge IA', $site_name);

    $body = implode("\n", [
        'Nova avaliação recebida do chat Judge IA.',
        '',
        '1. Clareza das informações: ' . ($clarity ?: 'N/A') . '/5',
        '2. Facilidade de uso da plataforma: ' . ($ease_of_use ?: 'N/A') . '/5',
        '3. Utilidade das orientações: ' . ($utility ?: 'N/A') . '/5',
        '4. Confiança no CEJUSC: ' . ($trust ?: 'N/A') . '/5',
        '5. Satisfação Geral: ' . ($satisfaction ?: 'N/A') . '/5',
        '',
        'Usuário: ' . $user_label,
        'Página: ' . ($page_url ?: 'Não informada'),
        '',
        'Comentários / Sugestões:',
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