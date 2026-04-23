<?php
if (!defined('ABSPATH')) exit;

class JudgeIA_OpenAI implements JudgeIA_Provider_Interface {

    public function send($message, $history = []) {

        $settings = get_option('judgeia_settings_provedores');
        $geral    = get_option('judgeia_settings_geral');

        $api_key  = $settings['openai_api_key'] ?? '';
        $model    = $settings['openai_model'] ?? 'gpt-4o-mini';
        $system_prompt = trim($geral['system_prompt'] ?? '');

        if (!$api_key) return false;

        $messages = [];

        if (!empty($system_prompt)) {
            $messages[] = [
                "role" => "system",
                "content" => $system_prompt
            ];
        }

        foreach ($history as $item) {
            $messages[] = [
                "role" => "user",
                "content" => $item['question']
            ];
            $messages[] = [
                "role" => "assistant",
                "content" => $item['answer']
            ];
        }

        $messages[] = [
            "role" => "user",
            "content" => $message
        ];

        $response = wp_remote_post("https://api.openai.com/v1/chat/completions", [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => json_encode([
                "model" => $model,
                "messages" => $messages
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) return false;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        $text = $data['choices'][0]['message']['content'] ?? false;
        $tokens = $data['usage']['total_tokens'] ?? 0;

        return [
            'content' => $text,
            'tokens'  => $tokens
        ];
    }
}

function judgeia_openai_send($message, $history = []) {
    $provider = new JudgeIA_OpenAI();
    return $provider->send($message, $history);
}