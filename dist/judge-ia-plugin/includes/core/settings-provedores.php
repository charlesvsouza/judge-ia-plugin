<?php

if (!defined('ABSPATH')) {
    exit;
}

function judgeia_register_settings_provedores() {

    register_setting(
        'judgeia_settings_group_provedores',
        'judgeia_settings_provedores',
        'judgeia_sanitize_provedores'
    );
}

add_action('admin_init', 'judgeia_register_settings_provedores');
/**
 * Aba Provedores
 */
function judgeia_render_tab_provedores() {

    $options = get_option('judgeia_settings_provedores');

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

        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}