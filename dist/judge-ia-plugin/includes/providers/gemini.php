<?php
if (!defined('ABSPATH')) exit;

class JudgeIA_Gemini implements JudgeIA_Provider_Interface {

    public function send($message, $history = []) {

        $settings = get_option('judgeia_settings_provedores');
        $geral    = get_option('judgeia_settings_geral');

        $api_key  = $settings['gemini_api_key'] ?? '';
        $model    = $settings['gemini_model'] ?? 'gemini-2.0-flash';
        $system_prompt = trim($geral['system_prompt'] ?? '');

        if (!$api_key) return false;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

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

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) return false;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? false;

        $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;

        return [
            'content' => $text,
            'tokens'  => $tokens
        ];
    }
}

function judgeia_gemini_send($message, $history = []) {
    $provider = new JudgeIA_Gemini();
    return $provider->send($message, $history);
}