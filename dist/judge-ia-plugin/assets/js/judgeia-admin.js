jQuery(document).ready(function($){

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

});