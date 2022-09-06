if(!! document.getElementById('wp-media-grid')){

    function getGridsAttachments(){
        let attachmentsIntval = setInterval(() => {
            let attachments = document.querySelectorAll('.attachments-browser .attachments .attachment');
            if(attachments.length > 0){
                clearInterval(attachmentsIntval);
                attachments.forEach(attachment => {
                    attachment.addEventListener('click', () => {
                        let selectedAttachments = document.querySelectorAll('.attachments-browser .attachments .attachment.selected');
                        if(selectedAttachments.length > 0){
                            createTermsButton();
                            bindSecondaryToolbarButtons();
                        } else {
                            removeTermsButton();
                        }
                    });
                });
            }
        }, 200);
    }

    function createTermsButton(){
        let existingButton = document.getElementById('setTermsButton');
        if(!existingButton){
            let toolBar = document.querySelector('.media-frame-toolbar .media-toolbar-secondary');
            termsButton = document.createElement('button');
            termsButton.setAttribute('type', 'button');
            termsButton.setAttribute('class', 'button media-button button-primary button-large');
            termsButton.setAttribute('id', 'setTermsButton');
            termsButton.innerHTML = 'Ajouter des tags aux photos selectionnées';
            toolBar.prepend(termsButton);
            termsButton.addEventListener('click', () => {
                displayTermsForm();
            });
        }
    }

    function bindSecondaryToolbarButtons(){
        let toolBarSecondaryButtons = document.querySelectorAll('.media-frame-toolbar .media-toolbar-secondary .button');
        if(toolBarSecondaryButtons.length > 0){
            toolBarSecondaryButtons.forEach(button => {
                if(button.getAttribute('id') != 'setTermsButton'){
                    button.addEventListener('click', () => {
                        removeTermsButton();
                    });
                }
            });
        }
    }

    function removeTermsButton(){
        let termsButton = document.getElementById('setTermsButton');
        if(termsButton){
            termsButton.remove();
        }
    }

    function displayTermsForm(){
        let exitstingForm = document.getElementById('attachmentsAddTerms');
        if(!exitstingForm){
            termsForm = document.createElement('div');
            termsForm.setAttribute('id', 'attachmentsAddTerms');
            termsForm.innerHTML = '<div class="choices"><ul class="themes"><p>Thématiques</p></ul><ul class="places"><p>Lieux</p></ul><ul class="seasons"><p>Saisons</p></ul></div><div class="actions"><button class="button button-primary apply">Valider</button><button class=" button close">Annuler</button></div>';

            getAttachmentsTerms(termsForm);

            document.getElementById('wpbody-content').append(termsForm);

            bindTermsFormActions(termsForm);

        } else {
            exitstingForm.classList.remove('hidden');
        }
    }

    function getAttachmentsTerms(termsForm){
        fetch('/wp-json/woody/attachments/terms')
            .then(response => response.json())
            .then(taxs => {
                console.log(taxs);
                if(!!taxs){
                    Object.entries(taxs).forEach(
                        ([taxName, tax]) => tax.forEach( term => {
                            let taxList = termsForm.querySelector('.' + taxName);
                            let listItem = document.createElement('li');
                            listItem.innerHTML = term.name;

                            let listBox = document.createElement('input');
                            listBox.setAttribute('type', 'checkbox');
                            listBox.setAttribute('value', term.id);
                            listItem.prepend(listBox);
                            taxList.append(listItem);
                        })
                    );

                }
            })
            .catch(error => {
                console.error('Attachments terms fetch: ' + error);

            });
    }

    function addTaxTerms(element, index, array){

    }

    function bindTermsFormActions(termsForm) {
        termsForm.querySelector('.button.close').addEventListener('click', () => {
            termsForm.classList.add('hidden');
        });
    }

    function bindLoadMoreButton(){
        let loadMoreIntval = setInterval(() => {
            let loadMoreWrapper = document.querySelector('.load-more-wrapper');
            if(!!loadMoreWrapper){
                clearInterval(loadMoreIntval);
                document.querySelector('.button.load-more').addEventListener('click', function(){
                    let jumpIntval = setInterval(() => {
                        if(!loadMoreWrapper.querySelector('.load-more-jump').classList.contains('hidden')){
                            clearInterval(jumpIntval);
                            getGridsAttachments();
                        }
                    }, 100);
                });
            }
        }, 200);
    }

    getGridsAttachments();
    bindLoadMoreButton();
}
