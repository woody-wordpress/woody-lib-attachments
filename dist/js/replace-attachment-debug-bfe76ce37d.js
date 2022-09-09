let replaceAttachment = document.getElementById('replaceAttachment');
if(!!replaceAttachment){

    let newMediaFrame =  document.getElementById('newMediaFrame');
    let newImageMarkup = document.getElementById('newMediaImg');
    let newAttachmentId = document.getElementById('newAttachmentId');
    let newFileTitle = document.getElementById('newFileTitle');
    let fromToIcon = document.getElementById('fromToIcon');

    let url = new URL(window.location.href);
    let currentId = url.searchParams.get('attachment_id');
    let mimeType = url.searchParams.get("mime_type");

    replaceAttachment.addEventListener('click', function(){
        var file_frame;

        let frameOptions = {
            title: 'Remplacer le média',
            button: {text: 'Choisir'},
            multiple: false
        }

        if(!!mimeType){
            frameOptions.library = {
                type : mimeType.replace('_', '/')
            }
        }

        file_frame = wp.media.frames.file_frame = wp.media(frameOptions);

        file_frame.on( 'select', function() {
            attachment = file_frame.state().get('selection').first().toJSON();
            if(!!newImageMarkup){
                newImageMarkup.setAttribute('src', attachment.url);
            }
            if(!!newFileTitle){
                newFileTitle.innerHTML = attachment.title;
            }
            replaceAttachment.classList.add('hidden');
            fromToIcon.classList.remove('hidden');
            newMediaFrame.classList.remove('hidden');
        });

        file_frame.open();
    });


    let submitNewAttachment = document.getElementById('submitNewAttachment');
    if(!!submitNewAttachment){
        submitNewAttachment.addEventListener('click', function(){
            fromToIcon.classList.remove('dashicons-arrow-down-alt');
            fromToIcon.classList.add('dashicons-update');
            fromToIcon.classList.add('spin');

            var customHeaders = new Headers();
            customHeaders.append('X-WP-Nonce', wpApiSettings.nonce);

            // Do action replace_meta
            fetch('/wp-json/woody/attachments/replace?search='+ currentId +'&replace=' + attachment.id, {
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
