let replaceAttachment = document.getElementById('replaceAttachment');
if(!!replaceAttachment){

    let newMediaFrame =  document.getElementById('newMediaFrame');
    let newAttachmentId = document.getElementById('newAttachmentId');

    replaceAttachment.addEventListener('click', function(){
        var file_frame;

        file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Remplacer le média',
            button: {text: 'Choisir'},
            multiple: false
        });

        file_frame.on( 'select', function() {
            attachment = file_frame.state().get('selection').first().toJSON();
            document.getElementById('newMediaImg').setAttribute('src', attachment.url);
            replaceAttachment.classList.add('hidden');
            newMediaFrame.classList.remove('hidden');
        });

        file_frame.open();
    });


    let submitNewAttachment = document.getElementById('submitNewAttachment');
    if(!!submitNewAttachment){
        submitNewAttachment.addEventListener('click', function(){
            newMediaFrame.querySelector('.dashicons').classList.remove('dashicons-arrow-down-alt');
            newMediaFrame.querySelector('.dashicons').classList.add('dashicons-update');
            newMediaFrame.querySelector('.dashicons').classList.add('spin');

            let url = new URL(window.location.href);
            let searchId = url.searchParams.get("attachment_id");

            var customHeaders = new Headers();
            customHeaders.append('X-WP-Nonce', wpApiSettings.nonce);

            // Do action replace_meta
            fetch('/wp-json/woody/attachments/replace?search='+ searchId +'&replace=' + attachment.id, {
                headers : customHeaders
            })
            .then(response => {
                document.getElementById('woodyMediapageslistTable').innerHTML = '<h3>Remplacement en cours - Cette opération peut prendre quelques minutes.<br/>Rafraichissez la page pour afficher une liste à jour</h3>';
                newMediaFrame.classList.add('hidden');
            })
            .catch(error => {
                console.error('Replace fetch: ' + error);
            });


        });
    }

    let cancelNewAttachment = document.getElementById('cancelNewAttachment');
    if(!!cancelNewAttachment){
        cancelNewAttachment.addEventListener('click', function(){
            newMediaFrame.classList.add('hidden');
        });
    }

}
