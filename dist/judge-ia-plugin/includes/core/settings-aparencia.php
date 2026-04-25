<?php
if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| REGISTRO
|--------------------------------------------------------------------------
*/
function judgeia_register_settings_aparencia() {

    register_setting(
        'judgeia_settings_group_aparencia',
        'judgeia_settings_aparencia',
        'judgeia_sanitize_aparencia'
    );
}
add_action('admin_init', 'judgeia_register_settings_aparencia');


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/
function judgeia_render_tab_aparencia() {

    $options = get_option('judgeia_settings_aparencia');

    $button_image = $options['button_image'] ?? '';
    $avatar_image = $options['avatar_image'] ?? '';
    ?>

    <form method="post" action="options.php">
        <?php settings_fields('judgeia_settings_group_aparencia'); ?>

        <table class="form-table">

            <tr>
                <th>Imagem do Botão</th>
                <td>
                    <input type="hidden"
                           name="judgeia_settings_aparencia[button_image]"
                           value="<?php echo esc_attr($button_image); ?>"
                           class="judgeia-image-field">

                    <button type="button" class="button judgeia-upload">
                        Selecionar imagem
                    </button>

                    <div style="margin-top:10px;">
                        <img src="<?php echo esc_url($button_image); ?>"
                             class="judgeia-preview"
                             style="max-width:100px;<?php echo empty($button_image) ? 'display:none;' : ''; ?>">
                    </div>
                </td>
            </tr>

            <tr>
                <th>Avatar da IA</th>
                <td>
                    <input type="hidden"
                           name="judgeia_settings_aparencia[avatar_image]"
                           value="<?php echo esc_attr($avatar_image); ?>"
                           class="judgeia-image-field">

                    <button type="button" class="button judgeia-upload">
                        Selecionar imagem
                    </button>

                    <div style="margin-top:10px;">
                        <img src="<?php echo esc_url($avatar_image); ?>"
                             class="judgeia-preview"
                             style="max-width:100px;<?php echo empty($avatar_image) ? 'display:none;' : ''; ?>">
                    </div>
                </td>
            </tr>

        </table>

        <?php submit_button(); ?>
    </form>

    <?php
}