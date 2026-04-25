<?php

if (!defined('ABSPATH')) {
    exit;
}

function judgeia_register_settings_provedores() {

    register_setting(
        'judgeia_settings_group_provedores',
        'judgeia_settings_provedores',
        [
            'type' => 'array',
            'sanitize_callback' => 'judgeia_sanitize_provedores',
            'default' => judgeia_get_default_settings_provedores(),
        ]
    );
}

add_action('admin_init', 'judgeia_register_settings_provedores');

add_action('wp_ajax_judgeia_test_provider_connection', 'judgeia_test_provider_connection');

function judgeia_test_provider_connection() {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sem permissão para executar este teste.']);
    }

    check_ajax_referer('judgeia_admin_nonce', 'nonce');

    $provider = sanitize_key($_POST['provider'] ?? '');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? '');

    if (!in_array($provider, ['gemini', 'openai'], true)) {
        wp_send_json_error(['message' => 'Provedor inválido.']);
    }

    if ($api_key === '') {
        wp_send_json_error(['message' => 'Informe a API Key para testar a conexão.']);
    }

    if ($provider === 'gemini') {
        if ($model === '') {
            $model = 'gemini-2.0-flash';
        }

        $model = preg_replace('#^models/#i', '', trim($model));

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Responda apenas com: OK'],
                    ],
                ],
            ],
        ];

        $versions = ['v1beta', 'v1'];
        $last_error = null;

        foreach ($versions as $api_version) {
            $url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$model}:generateContent?key={$api_key}";

            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Falha de rede ao conectar no Gemini: ' . $response->get_error_message()]);
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($body['error'])) {
                wp_send_json_success(['message' => 'Conexão com Gemini validada com sucesso.']);
            }

            $last_error = $body['error']['message'] ?? 'Erro desconhecido na API do Gemini.';

            $lower_message = strtolower((string)$last_error);
            $is_model_unavailable = (
                strpos($lower_message, 'is not found for api version') !== false
                || strpos($lower_message, 'not supported for generatecontent') !== false
                || (strpos($lower_message, 'model') !== false && strpos($lower_message, 'not found') !== false)
            );

            if (!$is_model_unavailable) {
                break;
            }
        }

        wp_send_json_error(['message' => 'Gemini retornou erro: ' . ($last_error ?: 'Erro desconhecido na API do Gemini.')]);
    }

    if ($model === '') {
        $model = 'gpt-4o-mini';
    }

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Reply with only: OK',
                ],
            ],
            'max_tokens' => 8,
        ]),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Falha de rede ao conectar na OpenAI: ' . $response->get_error_message()]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['error'])) {
        $message = $body['error']['message'] ?? 'Erro desconhecido na API da OpenAI.';
        wp_send_json_error(['message' => 'OpenAI retornou erro: ' . $message]);
    }

    wp_send_json_success(['message' => 'Conexão com OpenAI validada com sucesso.']);
}
/**
 * Aba Provedores
 */
function judgeia_render_tab_provedores() {

    $options = get_option('judgeia_settings_provedores');
    $options = is_array($options) ? $options : [];

    ?>
    <form method="post" action="options.php">
        <?php settings_fields('judgeia_settings_group_provedores'); ?>

        <table class="form-table">

            <tr>
                <th scope="row">Provedor Ativo</th>
                <td>
                    <select name="judgeia_settings_provedores[active_provider]">
                        <option value="gemini" <?php selected($options['active_provider'] ?? '', 'gemini'); ?>>
                            Gemini (Default)
                        </option>
                        <option value="openai" <?php selected($options['active_provider'] ?? '', 'openai'); ?>>
                            OpenAI
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">Gemini API Key</th>
                <td>
                    <input type="text"
                           size="50"
                           name="judgeia_settings_provedores[gemini_api_key]"
                           value="<?php echo esc_attr($options['gemini_api_key'] ?? ''); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">Gemini Model</th>
                <td>
                    <input type="text"
                           name="judgeia_settings_provedores[gemini_model]"
                           value="<?php echo esc_attr($options['gemini_model'] ?? 'gemini-2.0-flash'); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">OpenAI API Key</th>
                <td>
                    <input type="text"
                           size="50"
                           name="judgeia_settings_provedores[openai_api_key]"
                           value="<?php echo esc_attr($options['openai_api_key'] ?? ''); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">OpenAI Model</th>
                <td>
                    <input type="text"
                           name="judgeia_settings_provedores[openai_model]"
                           value="<?php echo esc_attr($options['openai_model'] ?? 'gpt-4o-mini'); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">Teste de Conexão</th>
                <td>
                    <button type="button" class="button" id="judgeia-test-connection">
                        Testar conexão da API
                    </button>
                    <p class="description" id="judgeia-test-connection-result" style="margin-top:8px;"></p>
                </td>
            </tr>

        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}