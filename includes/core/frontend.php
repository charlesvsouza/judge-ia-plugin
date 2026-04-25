<?php
if (!defined('ABSPATH')) exit;

function judgeia_enqueue_assets() {

    wp_enqueue_style(
        'judgeia-style',
        JUDGEIA_PLUGIN_URL . 'assets/css/judgeia-chat.css',
        [],
        JUDGEIA_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'judgeia-script',
        JUDGEIA_PLUGIN_URL . 'assets/js/judgeia-chat.js',
        [],
        JUDGEIA_PLUGIN_VERSION,
        true
    );

    $geral = get_option('judgeia_settings_geral');
    $welcome = $geral['welcome_message'] ?? '';

    wp_localize_script('judgeia-script', 'judgeia_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('judgeia_nonce'),
        'welcome'  => $welcome,
        'survey'   => [
            'success' => 'Obrigado pela sua avaliação.',
            'error'   => 'Não foi possível enviar sua avaliação agora.'
        ]
    ]);
}
add_action('wp_enqueue_scripts', 'judgeia_enqueue_assets');


function judgeia_render_widget() {

    $appearance = get_option('judgeia_settings_aparencia');

    $position      = $appearance['position'] ?? 'bottom-right';
    $button_image  = $appearance['button_image'] ?? '';
    $avatar_image  = $appearance['avatar_image'] ?? '';
    $primary_color = $appearance['primary_color'] ?? '#1e73be';
?>

<style>
:root { --judgeia-primary: <?php echo esc_attr($primary_color); ?>; }
</style>

<div class="judgeia-widget <?php echo esc_attr($position); ?>">

    <div id="judgeia-chat" class="judgeia-chat judgeia-hidden">

        <div class="judgeia-header">
            <div class="judgeia-header-left">
                <?php if ($avatar_image): ?>
                    <img src="<?php echo esc_url($avatar_image); ?>" class="judgeia-avatar">
                <?php endif; ?>

                <div class="judgeia-title-wrap">
                    <span class="judgeia-title">Judge IA</span>
                    <span class="judgeia-version">v<?php echo esc_html(JUDGEIA_PLUGIN_VERSION); ?></span>
                </div>
            </div>

        <div class="judgeia-header-actions">
            <button id="judgeia-clear" class="judgeia-btn" title="Limpar conversa">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
            </button>
            <button id="judgeia-minimize" class="judgeia-btn" title="Minimizar">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>
            </button>
            <button id="judgeia-toggle-size" class="judgeia-btn desktop-only" title="Expandir">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
            </button>
            <button id="judgeia-close" class="judgeia-btn danger" title="Fechar">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        </div>

        <div id="judgeia-messages" class="judgeia-messages"></div>

        <div id="judgeia-loader" class="judgeia-loader judgeia-hidden">
            <span></span><span></span><span></span>
        </div>

        <div id="judgeia-survey" class="judgeia-survey judgeia-hidden">
            <div class="judgeia-survey-card">
                <strong>Como foi sua experiência?</strong>
                <p>Avalie a qualidade da resposta da IA de 1 a 5 (1 = muito ruim, 5 = excelente).</p>

                <div class="judgeia-survey-rating" role="group" aria-label="Pesquisa de satisfação">
                    <button type="button" class="judgeia-rating-btn" data-rating="1">1</button>
                    <button type="button" class="judgeia-rating-btn" data-rating="2">2</button>
                    <button type="button" class="judgeia-rating-btn" data-rating="3">3</button>
                    <button type="button" class="judgeia-rating-btn" data-rating="4">4</button>
                    <button type="button" class="judgeia-rating-btn" data-rating="5">5</button>
                </div>

                <div class="judgeia-survey-scale" aria-hidden="true">
                    <span>Muito ruim</span>
                    <span>Excelente</span>
                </div>

                <textarea id="judgeia-survey-comment" rows="3" placeholder="Comentário opcional"></textarea>

                <div class="judgeia-survey-actions">
                    <button type="button" id="judgeia-survey-skip" class="judgeia-survey-secondary">Pular</button>
                    <button type="button" id="judgeia-survey-send" class="judgeia-survey-primary">Enviar avaliação</button>
                </div>

                <div id="judgeia-survey-status" class="judgeia-survey-status" aria-live="polite"></div>
            </div>
        </div>

        <div class="judgeia-input-area">
            <textarea id="judgeia-input" rows="1" placeholder="Digite sua pergunta..."></textarea>

            <button id="judgeia-send" class="judgeia-send-btn" aria-label="Enviar">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>

    </div>

    <button id="judgeia-button" class="judgeia-button">
        <?php if ($button_image): ?>
            <img src="<?php echo esc_url($button_image); ?>" alt="Assistente Jurídico">
        <?php else: ?>
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        <?php endif; ?>

        <span class="judgeia-tooltip">
             Assistente Jurídico
        </span>
    </button>

</div>

<?php
}
add_action('wp_footer', 'judgeia_render_widget');