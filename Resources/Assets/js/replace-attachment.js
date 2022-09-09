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

            let url = new URL(window.location.href);
            let searchId = url.searchParams.get("attachment_id");

            var customHeaders = new Headers();
            customHeaders.append('X-WP-Nonce', wpApiSettings.nonce);

            // Do action replace_meta
            fetch('/wp-json/woody/attachments/replace?search='+ searchId +'&replace=' + attachment.id, {
                headers : customHeaders
            })
            .then(response => console.log(response))
            .catch(error => {
                console.error('Replace fetch: ' + error);
            });

            document.getElementById('woodyMediapageslistTable').innerHTML = '<h3>Remplacement en cours - Cette opération peut prendre quelques minutes</h3><p>Rafraichissez la page pour mettre la liste à jour</p>';
        });
    }

    let cancelNewAttachment = document.getElementById('cancelNewAttachment');
    if(!!cancelNewAttachment){
        cancelNewAttachment.addEventListener('click', function(){
            newAttachmentId.value = '';
            newMediaFrame.classList.add('hidden');
        });
    }

}
