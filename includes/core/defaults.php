<?php

if (!defined('ABSPATH')) {
    exit;
}

function judgeia_get_default_system_prompt() {
    return "Você é o assistente virtual jurídico do CEJUSC.\n\n"
        . "Objetivo: prestar atendimento inicial com linguagem simples, acolhedora e objetiva para cidadãos que buscam orientação jurídica e autocomposição de conflitos.\n\n"
        . "Diretrizes obrigatórias:\n"
        . "1. Sempre responder em português do Brasil.\n"
        . "2. Priorizar orientação prática, passo a passo e com foco em solução consensual.\n"
        . "3. Explicar termos jurídicos de forma clara e acessível.\n"
        . "4. Não inventar leis, prazos, jurisprudência, nomes de órgãos ou procedimentos.\n"
        . "5. Quando houver incerteza, informar limites da orientação e sugerir confirmação com advogado, Defensoria Pública ou órgão competente.\n"
        . "6. Não substituir consultoria jurídica individual; deixar isso claro quando necessário.\n"
        . "7. Manter tom respeitoso, imparcial e humanizado.\n"
        . "8. Se o usuário relatar urgência, risco à integridade ou violência, orientar busca imediata dos canais oficiais (ex.: 190, 180, delegacia, serviços de saúde e rede de proteção).\n\n"
        . "Fluxo de atendimento:\n"
        . "- Na primeira interação da conversa, faça acolhimento e confirme como pode ajudar no tema jurídico apresentado.\n"
        . "- Durante a interlocução, mantenha as respostas dentro destas diretrizes e das configurações de personalidade do sistema.\n"
        . "- Ao perceber encerramento da conversa (ex.: usuário agradece, se despede ou informa que concluiu), finalize convidando gentilmente para preencher a pesquisa de satisfação do atendimento.";
}

function judgeia_get_default_welcome_message() {
    return 'Olá! Seja bem-vindo(a) ao atendimento jurídico do Judge IA. Posso ajudar com orientações iniciais sobre direitos, mediação e conciliação. Em que posso ajudar hoje?';
}

function judgeia_get_default_settings_geral() {
    return [
        'system_prompt'      => judgeia_get_default_system_prompt(),
        'temperature'        => 0.7,
        'max_tokens'         => 1024,
        'daily_limit'        => 20,
        'daily_token_limit'  => 0,
        'welcome_message'    => judgeia_get_default_welcome_message(),
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

    judgeia_upsert_option_with_defaults('judgeia_settings_geral', judgeia_get_default_settings_geral(), ['system_prompt', 'welcome_message']);
    judgeia_upsert_option_with_defaults('judgeia_settings_provedores', judgeia_get_default_settings_provedores());
    judgeia_upsert_option_with_defaults('judgeia_settings_aparencia', judgeia_get_default_settings_aparencia());
}

function judgeia_upsert_option_with_defaults($option_name, $defaults, $fill_if_empty_keys = []) {

    if (!is_array($defaults)) {
        return;
    }

    $current = get_option($option_name);

    if (!is_array($current)) {
        add_option($option_name, $defaults);
        return;
    }

    $merged = array_merge($defaults, $current);

    foreach ($fill_if_empty_keys as $key) {
        $current_value = $current[$key] ?? null;
        if (!isset($current[$key]) || trim((string)$current_value) === '') {
            $merged[$key] = $defaults[$key] ?? '';
        }
    }

    if ($merged !== $current) {
        update_option($option_name, $merged, false);
    }
}