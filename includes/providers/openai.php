<?php
if (!defined('ABSPATH')) exit;

class JudgeIA_OpenAI implements JudgeIA_Provider_Interface {

    private function is_rate_limited_error($result) {
        if (!is_array($result)) {
            return false;
        }

        $status_code = intval($result['status_code'] ?? 0);
        $error_code  = intval($result['error_code'] ?? 0);
        $error_type  = strtolower((string)($result['error_type'] ?? ''));
        $message     = strtolower((string)($result['message'] ?? ''));

        if ($status_code === 429 || $error_code === 429) {
            return true;
        }

        return (
            strpos($error_type, 'rate_limit') !== false
            || strpos($message, 'rate limit') !== false
            || strpos($message, 'quota') !== false
            || strpos($message, 'too many requests') !== false
        );
    }

    private function is_model_unavailable_error($result) {
        if (!is_array($result)) {
            return false;
        }

        $status_code = intval($result['status_code'] ?? 0);
        $error_type  = strtolower((string)($result['error_type'] ?? ''));
        $message     = strtolower((string)($result['message'] ?? ''));

        if ($status_code === 404) {
            return true;
        }

        return (
            strpos($error_type, 'model_not_found') !== false
            || (strpos($message, 'model') !== false && strpos($message, 'not found') !== false)
            || strpos($message, 'does not exist') !== false
        );
    }

    private function is_access_denied_error($result) {
        if (!is_array($result)) {
            return false;
        }

        $status_code = intval($result['status_code'] ?? 0);
        $error_code  = intval($result['error_code'] ?? 0);
        $error_type  = strtolower((string)($result['error_type'] ?? ''));
        $message     = strtolower((string)($result['message'] ?? ''));

        if ($status_code === 401 || $status_code === 403 || $error_code === 401 || $error_code === 403) {
            return true;
        }

        return (
            strpos($error_type, 'invalid_api_key') !== false
            || strpos($error_type, 'insufficient_quota') !== false
            || strpos($message, 'invalid api key') !== false
            || strpos($message, 'insufficient permissions') !== false
            || strpos($message, 'access denied') !== false
        );
    }

    private function request_chat_completions($api_key, $payload) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'kind' => 'network',
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = intval(wp_remote_retrieve_response_code($response));
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if (isset($data['error'])) {
            return [
                'ok' => false,
                'kind' => 'api',
                'status_code' => $status_code,
                'error_code' => intval($data['error']['code'] ?? $status_code),
                'error_type' => strtolower((string)($data['error']['type'] ?? '')),
                'message' => $data['error']['message'] ?? 'Erro desconhecido na API da OpenAI.',
                'raw' => $data['error'],
            ];
        }

        if (!is_array($data)) {
            return [
                'ok' => false,
                'kind' => 'format',
                'status_code' => $status_code,
                'message' => 'A resposta da API da OpenAI não possui JSON válido.',
                'raw_body' => $body_response,
            ];
        }

        if ($status_code >= 400) {
            return [
                'ok' => false,
                'kind' => 'api',
                'status_code' => $status_code,
                'error_code' => $status_code,
                'error_type' => 'http_error',
                'message' => 'Erro HTTP da API da OpenAI.',
                'raw' => $data,
            ];
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        $text = '';

        if (is_string($content)) {
            $text = $content;
        } elseif (is_array($content)) {
            foreach ($content as $part) {
                if (is_array($part) && (($part['type'] ?? '') === 'text') && isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        $text = trim((string)$text);
        $tokens = intval($data['usage']['total_tokens'] ?? 0);

        if ($text === '') {
            return [
                'ok' => false,
                'kind' => 'format',
                'status_code' => $status_code,
                'message' => 'A resposta da API da OpenAI não possui conteúdo textual.',
                'raw_body' => $body_response,
            ];
        }

        return [
            'ok' => true,
            'content' => $text,
            'tokens' => $tokens,
        ];
    }

    public function send($message, $history = []) {

        $settings = get_option('judgeia_settings_provedores');
        $geral    = get_option('judgeia_settings_geral');

        $api_key  = $settings['openai_api_key'] ?? '';
        $model    = $settings['openai_model'] ?? 'gpt-4o-mini';
        $system_prompt = trim($geral['system_prompt'] ?? '');

        if ($system_prompt === '' && function_exists('judgeia_get_default_system_prompt')) {
            $system_prompt = judgeia_get_default_system_prompt();
        }

        if (!$api_key) {
            return [
                'error' => 'OpenAI não configurada: informe a chave da API em Provedores.',
                'provider' => 'openai',
                'error_kind' => 'config',
            ];
        }

        $messages = [];

        if (!empty($system_prompt)) {
            $messages[] = [
                "role" => "system",
                "content" => $system_prompt
            ];
        }

        foreach ($history as $item) {
            $question = isset($item['question']) ? trim((string)$item['question']) : '';
            $answer   = isset($item['answer']) ? trim((string)$item['answer']) : '';

            if ($question === '' || $answer === '') {
                continue;
            }

            $messages[] = [
                "role" => "user",
                "content" => $question
            ];
            $messages[] = [
                "role" => "assistant",
                "content" => $answer
            ];
        }

        $messages[] = [
            "role" => "user",
            "content" => $message
        ];

        $temperature = floatval($geral['temperature'] ?? 0.7);
        $max_tokens  = intval($geral['max_tokens'] ?? 1024);

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        ];

        $result = $this->request_chat_completions($api_key, $payload);

        if (!empty($result['ok'])) {
            return [
                'content' => $result['content'],
                'tokens'  => intval($result['tokens'] ?? 0),
            ];
        }

        if (($result['kind'] ?? '') === 'network') {
            error_log('Judge IA (OpenAI WP Error): ' . ($result['message'] ?? 'Erro de rede.'));
            return [
                'error' => 'Falha de conexão com a API da OpenAI.',
                'provider' => 'openai',
                'error_kind' => 'network',
            ];
        }

        if ($this->is_rate_limited_error($result)) {
            error_log('Judge IA (OpenAI Rate Limit): ' . (isset($result['raw']) ? wp_json_encode($result['raw']) : ($result['message'] ?? '')));
            return [
                'error' => 'openai_rate_limited: A cota da API da OpenAI foi atingida. Aguarde alguns minutos e tente novamente.',
                'provider' => 'openai',
                'error_kind' => 'rate_limit',
            ];
        }

        if ($this->is_model_unavailable_error($result)) {
            error_log('Judge IA (OpenAI Model Unavailable): ' . (isset($result['raw']) ? wp_json_encode($result['raw']) : ($result['message'] ?? '')));
            return [
                'error' => 'O modelo OpenAI configurado não está disponível para esta chave/projeto. Atualize o modelo em Provedores.',
                'provider' => 'openai',
                'error_kind' => 'model_unavailable',
            ];
        }

        if ($this->is_access_denied_error($result)) {
            error_log('Judge IA (OpenAI Access Denied): ' . (isset($result['raw']) ? wp_json_encode($result['raw']) : ($result['message'] ?? '')));
            return [
                'error' => 'OpenAI rejeitou o acesso desta chave/projeto. Verifique chave, organização/permissões e faturamento.',
                'provider' => 'openai',
                'error_kind' => 'access_denied',
            ];
        }

        if (($result['kind'] ?? '') === 'format') {
            error_log('Judge IA (OpenAI Unexpected Response): ' . ($result['raw_body'] ?? ''));
            return [
                'error' => 'A resposta da API da OpenAI não possui o formato esperado.',
                'provider' => 'openai',
                'error_kind' => 'format',
            ];
        }

        if (isset($result['raw'])) {
            error_log('Judge IA (OpenAI API Error): ' . wp_json_encode($result['raw']));
        }

        return [
            'error' => 'Erro da API (OpenAI): ' . ($result['message'] ?? 'Erro desconhecido na API da OpenAI.'),
            'provider' => 'openai',
            'error_kind' => 'api',
        ];
    }
}

function judgeia_openai_send($message, $history = []) {
    $provider = new JudgeIA_OpenAI();
    return $provider->send($message, $history);
}