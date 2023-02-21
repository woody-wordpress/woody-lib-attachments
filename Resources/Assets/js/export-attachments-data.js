let submitExport = document.getElementById('submit_export');

if(!!submitExport){
    submitExport.addEventListener('click', function(e){
        e.preventDefault();
        let mimeTypeNodes = document.querySelectorAll('.mimetype-radio:checked');
        let mimeType = [];
        let langNodes = document.querySelectorAll('.lang-radio:checked');
        let lang = [];
        let fieldsNodes = document.querySelectorAll('.field-checkbox:checked');
        let fields = [];

        if(mimeTypeNodes.length > 0){
            mimeTypeNodes.forEach(function(mimeTypeNode){
                mimeType.push(mimeTypeNode.value);
            });
        }

        if(langNodes.length > 0){
            langNodes.forEach(function(langNode){
                lang.push(langNode.value);
            });
        }

        if(fieldsNodes.length > 0){
            fieldsNodes.forEach(function(fieldNode){
                fields.push(fieldNode.value);
            });
        }

        fetch('/wp-json/woody/attachments/exportdata', {
            method : 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify({
                mimetype: mimeType,
                lang: lang,
                fields: fields
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log(data);
        })
        .catch(error => {
            console.error('Attachments delete fetch: ' + error);
        });
    });
}
