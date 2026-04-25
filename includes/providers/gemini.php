<?php
if (!defined('ABSPATH')) exit;

class JudgeIA_Gemini implements JudgeIA_Provider_Interface {

    private function normalize_model_name($model) {
        $model = trim((string)$model);
        $model = preg_replace('#^models/#i', '', $model);
        return $model;
    }

    private function request_generate_content_for_version($api_key, $model, $body, $api_version) {

        $url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$model}:generateContent?key={$api_key}";

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
                'api_version' => $api_version,
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

    private function request_generate_content($api_key, $model, $body) {
        $model = $this->normalize_model_name($model);

        // Tenta primeiro v1beta por compatibilidade e, se necessário, tenta v1.
        $result = $this->request_generate_content_for_version($api_key, $model, $body, 'v1beta');
        if (!empty($result['ok'])) {
            return $result;
        }

        if ($this->is_model_unavailable_error($result)) {
            $fallback_result = $this->request_generate_content_for_version($api_key, $model, $body, 'v1');
            if (!empty($fallback_result['ok'])) {
                return $fallback_result;
            }
            return $fallback_result;
        }

        return $result;
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

    private function is_model_unavailable_error($result) {
        if (!is_array($result) || ($result['kind'] ?? '') !== 'api') {
            return false;
        }

        $message = strtolower((string)($result['message'] ?? ''));

        return (
            strpos($message, 'is not found for api version') !== false
            || strpos($message, 'not supported for generatecontent') !== false
            || strpos($message, 'model') !== false && strpos($message, 'not found') !== false
        );
    }

    private function is_system_instruction_payload_error($result) {
        if (!is_array($result) || ($result['kind'] ?? '') !== 'api') {
            return false;
        }

        $message = strtolower((string)($result['message'] ?? ''));

        return (
            strpos($message, 'unknown name "systeminstruction"') !== false
            || strpos($message, "unknown name 'systeminstruction'") !== false
            || (strpos($message, 'systeminstruction') !== false && strpos($message, 'cannot find field') !== false)
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
            // Formato aceito pela API Gemini (REST): system_instruction (snake_case).
            $body["system_instruction"] = [
                "parts" => [["text" => $system_prompt]]
            ];
        }

        $has_system_instruction = isset($body['system_instruction']);
        $body_without_system_instruction = $body;
        unset($body_without_system_instruction['system_instruction']);

        $attempt_models = [$this->normalize_model_name($model)];

        // Fallbacks comuns para reduzir falhas por indisponibilidade de modelo/API.
        foreach (['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'] as $fallback_model) {
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


            if ($has_system_instruction && $this->is_system_instruction_payload_error($result)) {
                error_log(sprintf(
                    'Judge IA (Gemini Payload): campo system_instruction rejeitado para model=%s; repetindo sem system instruction.',
                    $current_model
                ));

                $body = $body_without_system_instruction;
                $has_system_instruction = false;

                $retry_result = $this->request_generate_content($api_key, $current_model, $body);
                if (!empty($retry_result['ok'])) {
                    return [
                        'content' => $retry_result['content'],
                        'tokens'  => $retry_result['tokens'],
                    ];
                }

                $result = $retry_result;
                $last_error = $result;
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

            if ($this->is_model_unavailable_error($result)) {
                error_log(sprintf(
                    'Judge IA (Gemini Model Unavailable): model=%s, api=%s, error=%s',
                    $current_model,
                    $result['api_version'] ?? 'unknown',
                    isset($result['raw']) ? wp_json_encode($result['raw']) : ($result['message'] ?? 'unknown')
                ));
                continue;
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

        if ($this->is_model_unavailable_error($last_error)) {
            return [
                'error' => 'O modelo Gemini configurado não está disponível para sua chave/projeto nesta versão da API. Atualize o modelo em Provedores (ex.: gemini-2.0-flash) ou use OpenAI.'
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