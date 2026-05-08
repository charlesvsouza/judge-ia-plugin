<?php

if (!defined('ABSPATH')) {
    exit;
}

function judgeia_register_settings_geral() {

    register_setting(
        'judgeia_settings_group_geral',
        'judgeia_settings_geral',
        'judgeia_sanitize_geral'
    );
}

add_action('admin_init', 'judgeia_register_settings_geral');

function judgeia_render_tab_geral() {

    $options = get_option('judgeia_settings_geral');
    ?>
    <form method="post" action="options.php">
        <?php settings_fields('judgeia_settings_group_geral'); ?>

        <table class="form-table">

            <tr>
                <th scope="row">System Prompt</th>
                <td>
                    <textarea name="judgeia_settings_geral[system_prompt]"
                              rows="5"
                              cols="60"><?php echo esc_textarea($options['system_prompt'] ?? ''); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">Temperature</th>
                <td>
                    <input type="number"
                           step="0.1"
                           min="0"
                           max="2"
                           name="judgeia_settings_geral[temperature]"
                           value="<?php echo esc_attr($options['temperature'] ?? 0.7); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">Max Tokens</th>
                <td>
                    <input type="number"
                           min="1"
                           name="judgeia_settings_geral[max_tokens]"
                           value="<?php echo esc_attr($options['max_tokens'] ?? 1024); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">Limite de Requisições por Dia</th>
                <td>
                    <input type="number"
                           min="0"
                           name="judgeia_settings_geral[daily_limit]"
                           value="<?php echo esc_attr($options['daily_limit'] ?? 20); ?>">
                    <p class="description">
                        0 = ilimitado. Aplica-se a usuários logados e visitantes.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">Limite Diário de Tokens</th>
                <td>
                    <input type="number"
                           min="0"
                           name="judgeia_settings_geral[daily_token_limit]"
                           value="<?php echo esc_attr($options['daily_token_limit'] ?? 0); ?>">
                    <p class="description">
                        0 = ilimitado. Este limite soma os tokens de respostas geradas no dia.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">Mensagem de Boas-vindas</th>
                <td>
                    <textarea name="judgeia_settings_geral[welcome_message]"
                              rows="3"
                              cols="60"><?php echo esc_textarea($options['welcome_message'] ?? ''); ?></textarea>
                    <p class="description">
                        Mensagem exibida ao abrir o chat pela primeira vez.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">E-mail da Pesquisa</th>
                <td>
                    <input type="email"
                           size="50"
                           name="judgeia_settings_geral[feedback_email]"
                           value="<?php echo esc_attr($options['feedback_email'] ?? ''); ?>">
                    <p class="description">
                        Se vazio, a pesquisa será enviada para o e-mail principal do WordPress.
                    </p>
                </td>
            </tr>

        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}