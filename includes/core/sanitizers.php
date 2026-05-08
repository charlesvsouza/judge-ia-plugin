<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Sanitização - Geral
|--------------------------------------------------------------------------
*/
function judgeia_sanitize_geral($input) {

    $defaults = judgeia_get_default_settings_geral();
    $output   = [];

    $output['system_prompt'] = isset($input['system_prompt'])
        ? wp_kses_post($input['system_prompt'])
        : $defaults['system_prompt'];

    $output['temperature'] = isset($input['temperature'])
        ? floatval($input['temperature'])
        : $defaults['temperature'];

    $output['max_tokens'] = isset($input['max_tokens'])
        ? intval($input['max_tokens'])
        : $defaults['max_tokens'];

    // 🔹 CORREÇÃO: Sanitização do limite diário
    $output['daily_limit'] = isset($input['daily_limit'])
        ? max(0, intval($input['daily_limit']))
        : ($defaults['daily_limit'] ?? 20);

    $output['daily_token_limit'] = isset($input['daily_token_limit'])
        ? max(0, intval($input['daily_token_limit']))
        : ($defaults['daily_token_limit'] ?? 0);

    $output['welcome_message'] = isset($input['welcome_message'])
        ? sanitize_textarea_field($input['welcome_message'])
        : ($defaults['welcome_message'] ?? '');

    $output['feedback_email'] = isset($input['feedback_email'])
        ? sanitize_email($input['feedback_email'])
        : ($defaults['feedback_email'] ?? '');

    return $output;
}


/*
|--------------------------------------------------------------------------
| Sanitização - Provedores
|--------------------------------------------------------------------------
*/
function judgeia_sanitize_provedores($input) {

    try {
        return judgeia_sanitize_provedores_internal($input);
    } catch (Throwable $exception) {
        error_log('Judge IA (Save Providers Fatal): ' . $exception->getMessage());

        if (function_exists('add_settings_error')) {
            add_settings_error(
                'judgeia_settings_provedores',
                'judgeia_settings_provedores_exception',
                'Falha interna ao salvar os provedores. Os dados anteriores foram mantidos.',
                'error'
            );
        }

        $current = get_option('judgeia_settings_provedores');
        if (is_array($current)) {
            return array_merge(judgeia_get_default_settings_provedores(), $current);
        }

        return judgeia_get_default_settings_provedores();
    }
}

function judgeia_sanitize_provedores_internal($input) {

    $defaults = judgeia_get_default_settings_provedores();
    $current  = get_option('judgeia_settings_provedores');
    $current  = is_array($current) ? $current : [];
    $output   = array_merge($defaults, $current);

    if (!is_array($input)) {
        add_settings_error(
            'judgeia_settings_provedores',
            'judgeia_settings_provedores_invalid_payload',
            'Falha ao salvar os provedores: formato de dados invalido.',
            'error'
        );

        return $output;
    }

    $allowed_providers = ['gemini', 'openai'];
    $active_provider = sanitize_key(wp_unslash($input['active_provider'] ?? ''));

    $output['active_provider'] = in_array($active_provider, $allowed_providers, true)
        ? $active_provider
        : $defaults['active_provider'];

    $output['gemini_api_key'] = judgeia_sanitize_settings_scalar($input['gemini_api_key'] ?? '');
    $output['gemini_model']   = judgeia_sanitize_settings_scalar($input['gemini_model'] ?? $defaults['gemini_model']);

    $output['openai_api_key'] = judgeia_sanitize_settings_scalar($input['openai_api_key'] ?? '');
    $output['openai_model']   = judgeia_sanitize_settings_scalar($input['openai_model'] ?? $defaults['openai_model']);

    return $output;
}

function judgeia_sanitize_settings_scalar($value) {
    if (is_array($value) || is_object($value)) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string)$value));
}


/*
|--------------------------------------------------------------------------
| Sanitização - Aparência
|--------------------------------------------------------------------------
*/
function judgeia_sanitize_aparencia($input) {

    $defaults = judgeia_get_default_settings_aparencia();
    $output   = [];

    // Cor
    $color = sanitize_hex_color($input['primary_color'] ?? '');
    $output['primary_color'] = $color ? $color : ($defaults['primary_color'] ?? '#1e73be');

    // Posição
    $allowed_positions = ['bottom-right', 'bottom-left'];
    $output['position'] = in_array($input['position'] ?? '', $allowed_positions, true)
        ? $input['position']
        : ($defaults['position'] ?? 'bottom-right');

    // NOVO - Imagem do botão
    $output['button_image'] = isset($input['button_image'])
        ? esc_url_raw($input['button_image'])
        : '';

    // NOVO - Avatar da IA
    $output['avatar_image'] = isset($input['avatar_image'])
        ? esc_url_raw($input['avatar_image'])
        : '';

    return $output;
}