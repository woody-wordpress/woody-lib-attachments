let replaceAttachment = document.getElementById('replaceAttachment')
if(!!replaceAttachment){

    replaceAttachment.addEventListener('click', function(){
        var file_frame;

        file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Remplacer le média',
            button: {text: 'Choisir'},
            multiple: false
        });

        file_frame.on( 'select', function() {
            attachment = file_frame.state().get('selection').first().toJSON();
            document.getElementById('newAttachmentId').value = attachment.id;
        });

        file_frame.open();
    })

    let submitNewAttachment = document.getElementById('submitNewAttachment');
    if(!!submitNewAttachment){
        submitNewAttachment.addEventListener('click', function(){
            // Do action replace_meta
            // Redirect to /wp/wp-admin/admin.php?attachment_id={{attachment.id}}&page=woody-pages-using-media
        });
    }

}
