<?php

if (!defined('ABSPATH')) {
    exit;
}

function judgeia_get_default_settings_geral() {
    return [
        'system_prompt'      => '',
        'temperature'        => 0.7,
        'max_tokens'         => 1024,
        'daily_limit'        => 20,
        'daily_token_limit'  => 0,
        'welcome_message'    => '',
        'feedback_email'     => '',
    ];
}

function judgeia_get_default_settings_provedores() {
    return [
        'active_provider' => 'gemini',
        'gemini_api_key'  => '',
        'gemini_model'    => 'gemini-2.0-flash',
        'openai_api_key'  => '',
        'openai_model'    => 'gpt-4o-mini',
    ];
}

function judgeia_get_default_settings_aparencia() {
    return [
        'primary_color' => '#1e73be',
        'button_image'  => '',
        'avatar_image'  => '',
        'position'      => 'bottom-right',
    ];
}

function judgeia_initialize_defaults() {

    if (!get_option('judgeia_settings_geral')) {
        add_option('judgeia_settings_geral', judgeia_get_default_settings_geral());
    }

    if (!get_option('judgeia_settings_provedores')) {
        add_option('judgeia_settings_provedores', judgeia_get_default_settings_provedores());
    }

    if (!get_option('judgeia_settings_aparencia')) {
        add_option('judgeia_settings_aparencia', judgeia_get_default_settings_aparencia());
    }
}