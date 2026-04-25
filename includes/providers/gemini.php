<?php
if (!defined('ABSPATH')) exit;

class JudgeIA_Gemini implements JudgeIA_Provider_Interface {

    private function request_generate_content($api_key, $model, $body) {

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
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
            $error_message = $data['error']['message'] ?? 'Erro desconhecido na API do Gemini.';
            $error_status = strtolower((string)($data['error']['status'] ?? ''));
            $error_code = intval($data['error']['code'] ?? $status_code);

            return [
                'ok' => false,
                'kind' => 'api',
                'status_code' => $status_code,
                'error_code' => $error_code,
                'error_status' => $error_status,
                'message' => $error_message,
                'raw' => $data['error'],
            ];
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? false;
        $tokens = intval($data['usageMetadata']['totalTokenCount'] ?? 0);

        if ($text === false) {
            return [
                'ok' => false,
                'kind' => 'format',
                'message' => 'A resposta da API do Gemini não possui o formato esperado.',
                'raw_body' => $body_response,
            ];
        }

        return [
            'ok' => true,
            'content' => $text,
            'tokens' => $tokens,
        ];
    }

    private function is_access_denied_error($result) {
        if (!is_array($result) || ($result['kind'] ?? '') !== 'api') {
            return false;
        }

        $status_code = intval($result['status_code'] ?? 0);
        $error_code = intval($result['error_code'] ?? 0);
        $error_status = strtolower((string)($result['error_status'] ?? ''));
        $message = strtolower((string)($result['message'] ?? ''));

        if ($status_code === 403 || $error_code === 403 || $error_status === 'permission_denied') {
            return true;
        }

        return (
            strpos($message, 'denied access') !== false
            || strpos($message, 'permission denied') !== false
            || strpos($message, 'not allowed') !== false
            || strpos($message, 'insufficient permission') !== false
        );
    }

    public function send($message, $history = []) {

        $settings = get_option('judgeia_settings_provedores');
        $geral    = get_option('judgeia_settings_geral');

        $api_key  = $settings['gemini_api_key'] ?? '';
        $model    = $settings['gemini_model'] ?? 'gemini-2.0-flash';
        $system_prompt = trim($geral['system_prompt'] ?? '');

        if (!$api_key) return false;

        $contents = [];

        foreach ($history as $item) {
            $contents[] = [
                "role" => "user",
                "parts" => [["text" => $item['question']]]
            ];
            $contents[] = [
                "role" => "model",
                "parts" => [["text" => $item['answer']]]
            ];
        }

        $contents[] = [
            "role" => "user",
            "parts" => [["text" => $message]]
        ];

        $body = [
            "contents" => $contents
        ];

        if (!empty($system_prompt)) {
            $body["systemInstruction"] = [
                "parts" => [["text" => $system_prompt]]
            ];
        }

        $attempt_models = [$model];

        // Fallbacks comuns para contas sem acesso a modelos mais novos.
        foreach (['gemini-1.5-flash', 'gemini-1.5-pro'] as $fallback_model) {
            if (!in_array($fallback_model, $attempt_models, true)) {
                $attempt_models[] = $fallback_model;
            }
        }

        $last_error = null;

        foreach ($attempt_models as $current_model) {
            $result = $this->request_generate_content($api_key, $current_model, $body);

            if (!empty($result['ok'])) {
                return [
                    'content' => $result['content'],
                    'tokens'  => $result['tokens'],
                ];
            }

            $last_error = $result;

            if (($result['kind'] ?? '') === 'network') {
                error_log('Judge IA (Gemini WP Error): ' . ($result['message'] ?? 'Erro de rede.'));
                return ['error' => 'Falha de conexão com a API do Gemini.'];
            }

            if (($result['kind'] ?? '') === 'format') {
                error_log('Judge IA (Gemini Unexpected Response): ' . ($result['raw_body'] ?? ''));
                return ['error' => 'A resposta da API do Gemini não possui o formato esperado.'];
            }

            // So tenta fallback quando o problema e acesso/permissao do projeto/modelo.
            if (!$this->is_access_denied_error($result)) {
                break;
            }

            error_log(sprintf(
                'Judge IA (Gemini Access Denied): model=%s, error=%s',
                $current_model,
                isset($result['raw']) ? wp_json_encode($result['raw']) : ($result['message'] ?? 'unknown')
            ));
        }

        if ($this->is_access_denied_error($last_error)) {
            return [
                'error' => 'Gemini bloqueou o projeto/chave atual (acesso negado). Verifique faturamento, região e permissões da chave no Google AI Studio/Cloud ou troque para OpenAI em Provedores.'
            ];
        }

        $error_message = is_array($last_error)
            ? ($last_error['message'] ?? 'Erro desconhecido na API do Gemini.')
            : 'Erro desconhecido na API do Gemini.';

        if (is_array($last_error) && isset($last_error['raw'])) {
            error_log('Judge IA (Gemini API Error): ' . wp_json_encode($last_error['raw']));
        }

        return ['error' => 'Erro da API (Gemini): ' . $error_message];
    }
}

function judgeia_gemini_send($message, $history = []) {
    $provider = new JudgeIA_Gemini();
    return $provider->send($message, $history);
}