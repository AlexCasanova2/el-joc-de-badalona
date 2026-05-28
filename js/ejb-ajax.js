jQuery(document).ready(function ($) {

    function animarPunts(element, textBase) {
        let punts = 0;
        return setInterval(function () {
            punts = (punts + 1) % 4;
            element.text(textBase + '.'.repeat(punts));
        }, 500); // velocitat de canvi dels punts (500ms)
    }

    $('.pregunta-form').submit(function (e) {
        e.preventDefault();

        let form = $(this);
        let submitBtn = form.find('button[type="submit"]');
        let preguntaId = form.find('input[name^="pregunta_id_"]').val();
        let respuesta = form.find('input[type="radio"]:checked').val();

        if (!respuesta) {
            alert('Si us plau, selecciona una opció.');
            return;
        }

        let data = {
            action: 'ejb_guardar_respuesta_ajax',
            pregunta_id: preguntaId,
            respuesta: respuesta,
            nonce: ejb_ajax.nonce
        };

        submitBtn.prop('disabled', true);

        // Inicia animació
        let intervalAnimacio = animarPunts(submitBtn, 'Enviant');

        $.ajax({
            url: ejb_ajax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                clearInterval(intervalAnimacio);
                if (response.success) {
                    form.html('<p class="respuesta-ok">' + response.data + '</p>');
                } else {
                    form.html('<p class="respuesta-error">' + response.data + '</p>');
                    submitBtn.prop('disabled', false).text('Enviar resposta');
                }
            },
            error: function(xhr, status, error) {
                clearInterval(intervalAnimacio);
                console.error('Error AJAX:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Hi ha hagut un error inesperat, prova-ho de nou.');
                submitBtn.prop('disabled', false).text('Enviar resposta');
            }
        });
    });
});

