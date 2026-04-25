jQuery(document).ready(function($){

    const providerForm = $('#judgeia-provider-settings-form');

    if (providerForm.length && typeof judgeiaAdminData !== 'undefined') {
        providerForm.on('submit', function(e){
            e.preventDefault();

            const submitButton = providerForm.find('button[type="submit"], input[type="submit"]');
            const saveResult = $('#judgeia-provider-save-result');

            const activeProvider = providerForm.find('select[name="judgeia_settings_provedores[active_provider]"]').val() || 'gemini';
            const geminiKey = providerForm.find('input[name="judgeia_settings_provedores[gemini_api_key]"]').val() || '';
            const geminiModel = providerForm.find('input[name="judgeia_settings_provedores[gemini_model]"]').val() || '';
            const openaiKey = providerForm.find('input[name="judgeia_settings_provedores[openai_api_key]"]').val() || '';
            const openaiModel = providerForm.find('input[name="judgeia_settings_provedores[openai_model]"]').val() || '';

            submitButton.prop('disabled', true);
            saveResult.css('color', '#1d2327').text('Salvando configuracoes...');

            $.post(judgeiaAdminData.ajaxUrl, {
                action: 'judgeia_save_provider_settings',
                nonce: judgeiaAdminData.nonce,
                active_provider: activeProvider,
                gemini_api_key_encoded: btoa(unescape(encodeURIComponent(geminiKey))),
                gemini_model: geminiModel,
                openai_api_key_encoded: btoa(unescape(encodeURIComponent(openaiKey))),
                openai_model: openaiModel
            })
            .done(function(response){
                if (response && response.success) {
                    saveResult.css('color', '#1a7f37').text(response.data.message || 'Configuracoes salvas com sucesso.');
                    return;
                }

                const message = response && response.data && response.data.message
                    ? response.data.message
                    : 'Falha ao salvar as configuracoes.';
                saveResult.css('color', '#b42318').text(message);
            })
            .fail(function(){
                saveResult.css('color', '#b42318').text('Falha de comunicacao com o servidor WordPress ao salvar as configuracoes.');
            })
            .always(function(){
                submitButton.prop('disabled', false);
            });
        });
    }

    $('.judgeia-upload').on('click', function(e){

        e.preventDefault();

        const button = $(this);
        const container = button.closest('td');
        const inputField = container.find('.judgeia-image-field');
        const preview = container.find('.judgeia-preview');

        const frame = wp.media({
            title: 'Selecionar imagem',
            button: {
                text: 'Usar esta imagem'
            },
            multiple: false
        });

        frame.on('select', function(){

            const attachment = frame.state().get('selection').first().toJSON();

            inputField.val(attachment.url);
            preview.attr('src', attachment.url);
            preview.show();

        });

        frame.open();

    });

    $('#judgeia-test-connection').on('click', function(){

        if (typeof judgeiaAdminData === 'undefined') {
            return;
        }

        const button = $(this);
        const result = $('#judgeia-test-connection-result');

        const provider = $('select[name="judgeia_settings_provedores[active_provider]"]').val() || 'gemini';
        const geminiKey = $('input[name="judgeia_settings_provedores[gemini_api_key]"]').val() || '';
        const geminiModel = $('input[name="judgeia_settings_provedores[gemini_model]"]').val() || '';
        const openaiKey = $('input[name="judgeia_settings_provedores[openai_api_key]"]').val() || '';
        const openaiModel = $('input[name="judgeia_settings_provedores[openai_model]"]').val() || '';

        let apiKey = '';
        let model = '';

        if (provider === 'openai') {
            apiKey = openaiKey;
            model = openaiModel;
        } else {
            apiKey = geminiKey;
            model = geminiModel;
        }

        button.prop('disabled', true).text('Testando...');
        result.css('color', '#1d2327').text('Executando teste de conexão...');

        $.post(judgeiaAdminData.ajaxUrl, {
            action: 'judgeia_test_provider_connection',
            nonce: judgeiaAdminData.nonce,
            provider: provider,
            api_key: apiKey,
            model: model
        })
        .done(function(response){
            if (response && response.success) {
                result.css('color', '#1a7f37').text(response.data.message || 'Conexão validada com sucesso.');
                return;
            }

            const message = response && response.data && response.data.message
                ? response.data.message
                : 'Falha ao validar a conexão.';
            result.css('color', '#b42318').text(message);
        })
        .fail(function(){
            result.css('color', '#b42318').text('Falha de comunicação com o servidor WordPress.');
        })
        .always(function(){
            button.prop('disabled', false).text('Testar conexão da API');
        });
    });

});