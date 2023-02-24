// let submitExport = document.getElementById('submit_export');

// if(!!submitExport){
//     submitExport.addEventListener('click', function(e){
//         document.body.classList.remove('windowReady');
//         e.preventDefault();
//         let mimeTypeNodes = document.querySelectorAll('.mimetype-radio:checked');
//         let mimeType = [];
//         let languageNodes = document.querySelectorAll('.language-radio:checked');
//         let language = [];
//         let fieldsNodes = document.querySelectorAll('.field-checkbox:checked');
//         let fields = [];

//         if(mimeTypeNodes.length > 0){
//             mimeTypeNodes.forEach(function(mimeTypeNode){
//                 mimeType.push(mimeTypeNode.value);
//             });
//         }

//         if(languageNodes.length > 0){
//             languageNodes.forEach(function(languageNode){
//                 language.push(languageNode.value);
//             });
//         }

//         if(fieldsNodes.length > 0){
//             fieldsNodes.forEach(function(fieldNode){
//                 fields.push(fieldNode.value);
//             });
//         }

//         fetch('/wp-json/woody/attachments/exportdata', {
//             method : 'POST',
//             headers: {
//                 'Accept': 'application/json',
//                 'Content-Type': 'application/json',
//                 'X-WP-Nonce': wpApiSettings.nonce
//             },
//             body: JSON.stringify({
//                 mimetype: mimeType,
//                 language: language,
//                 fields: fields
//             })
//         })
//         .then(response => response.json())
//         .then(filepath => {
//             document.body.classList.add('windowReady');
//             window.open(window.location.origin + filepath.replace('home/admin/www/wordpress/current/web/', ''), '_blank');
//         })
//         .catch(error => {
//             console.error('Attachments delete fetch: ' + error);
//         });
//     });
// }
