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

    $defaults = judgeia_get_default_settings_provedores();
    $output   = [];

    $allowed_providers = ['gemini', 'openai'];

    $output['active_provider'] = in_array($input['active_provider'] ?? '', $allowed_providers, true)
        ? $input['active_provider']
        : $defaults['active_provider'];

    $output['gemini_api_key'] = sanitize_text_field($input['gemini_api_key'] ?? '');
    $output['gemini_model']   = sanitize_text_field($input['gemini_model'] ?? $defaults['gemini_model']);

    $output['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
    $output['openai_model']   = sanitize_text_field($input['openai_model'] ?? $defaults['openai_model']);

    return $output;
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