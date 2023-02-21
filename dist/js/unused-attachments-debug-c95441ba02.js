let deleteSubmit = document.getElementById('delete_unused');

if(!!deleteSubmit){
    deleteSubmit.addEventListener('click', function(e){
        e.preventDefault();
        let ids = [];
        let selectedItems = document.querySelectorAll('.media-checkbox:checked');
        if(!!selectedItems){
            if (selectedItems.length > 10 || selectedItems.length == 0){
                alert('Le nombre d\'images à supprimer doit être compris entre 1 et 10 (Sélection actuelle : '+ selectedItems.length + ')');
            } else {

                if(confirm('Êtes vous sur de vouloir supprimer ces '+ selectedItems.length + ' images ? Cette action est irreversible')){
                    document.body.classList.remove('windowReady');
                selectedItems.forEach(function(selectedItem) {
                    ids.push(selectedItem.value);
                });

                fetch('/wp-json/woody/attachments/delete', {
                    method : 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    },
                    body: JSON.stringify({
                        ids: ids
                    })
                })
                .then(response => response.json())
                .then(deleted => {
                    console.log(deleted);
                    window.location.href = window.location.href;
                })
                .catch(error => {
                    console.error('Attachments delete fetch: ' + error);
                });
                };
            }
        }
    });
}
