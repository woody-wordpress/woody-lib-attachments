if (!!document.getElementById('wp-media-grid')) {

    function getGridsAttachments() {
        let attachmentsIntval = setInterval(() => {
            let attachments = document.querySelectorAll('.attachments-browser .attachments .attachment');
            if (attachments.length > 0) {
                clearInterval(attachmentsIntval);
                attachments.forEach(attachment => {
                    attachment.addEventListener('click', () => {
                        let selectedAttachments = document.querySelectorAll('.attachments-browser .attachments .attachment.selected');
                        if (selectedAttachments.length > 0) {
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

    function createTermsButton() {
        let existingButton = document.getElementById('setTermsButton');
        if (!existingButton) {
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

    function bindSecondaryToolbarButtons() {
        let toolBarSecondaryButtons = document.querySelectorAll('.media-frame-toolbar .media-toolbar-secondary .button');
        if (toolBarSecondaryButtons.length > 0) {
            toolBarSecondaryButtons.forEach(button => {
                if (button.getAttribute('id') != 'setTermsButton') {
                    button.addEventListener('click', () => {
                        removeTermsButton();
                    });
                }
            });
        }
    }

    function removeTermsButton() {
        let termsButton = document.getElementById('setTermsButton');
        if (termsButton) {
            termsButton.remove();
        }
    }

    function displayTermsForm() {
        let exitstingForm = document.getElementById('attachmentsAddTerms');
        if (!exitstingForm) {
            termsForm = document.createElement('div');
            termsForm.setAttribute('id', 'attachmentsAddTerms');
            termsForm.innerHTML = '<div class="choices"><div><p>Thématiques</p><ul class="themes" id="terms-list"></ul></div><div><p>Lieux</p><ul class="places" id="terms-list"></ul></div><div><p>Circonstances</p><ul class="seasons" id="terms-list"></ul></div><div><p>Cibles</p><ul class="targets" id="terms-list"></ul></div></div><div class="actions"><button class="button button-primary apply">Valider</button><button class=" button close">Annuler</button></div>';

            getAttachmentsTerms(termsForm);
        } else {
            exitstingForm.classList.remove('hidden');
        }
    }

    function getAttachmentsTerms(termsForm) {

        var customHeaders = new Headers();
        customHeaders.append('X-WP-Nonce', wpApiSettings.nonce);

        const bodyClassesList = document.body.classList;
        const parsedClassLang = [...bodyClassesList].filter(bodyClass => bodyClass.includes('pll-lang-'));
        if (parsedClassLang && parsedClassLang[0]) {
            const currentAdminLang = parsedClassLang[0] ? parsedClassLang[0].split('-')[2] : 'fr';
            fetch(`/wp-json/woody/attachments/terms/get?lang=${currentAdminLang}`, {
                headers: customHeaders
            })
                .then(response => response.json())
                .then(taxs => {
                    if (!!taxs) {
                        for (let key in taxs) {
                            let taxList = termsForm.querySelector('.' + key);

                            if (taxList !== null) {
                                taxList.innerHTML = taxs[key];
                            }
                        }

                        document.getElementById('wpbody-content').append(termsForm);
                        let checkboxes = termsForm.querySelectorAll('input[type="checkbox"]');
                        if (checkboxes.length > 0) {
                            bindTermsFormActions(termsForm, checkboxes);
                        }
                    }
                })
                .catch(error => {
                    console.error('Attachments terms fetch: ' + error);
                });
        }
    }


    function bindTermsFormActions(termsForm, checkboxes) {
        termsForm.querySelector('.button.close').addEventListener('click', () => {
            termsForm.classList.add('hidden');
            checkboxes.forEach(element => {
                element.checked = false;
            });
        });

        termsForm.querySelector('.button.apply').addEventListener('click', () => {
            setPostTerms(checkboxes);
        });
    }

    function setPostTerms(checkboxes) {
        let attach_ids = [];
        let terms_ids = [];
        let selectedAttachments = document.querySelectorAll('.attachments-browser .attachments .attachment.selected');

        if (selectedAttachments.length > 0) {
            selectedAttachments.forEach(element => {
                attach_ids.push(element.dataset.id);
            });
        }

        if (checkboxes.length > 0) {
            checkboxes.forEach(element => {
                if (element.checked) {
                    terms_ids.push(element.value)
                }
            });
        }

        attach_ids.join(',');
        terms_ids.join(',');

        var customHeaders = new Headers();
        customHeaders.append('X-WP-Nonce', wpApiSettings.nonce);

        fetch('/wp-json/woody/attachments/terms/set?attach_ids=' + attach_ids + '&terms_ids=' + terms_ids, {
            headers: customHeaders
        })
            .then(response => response.json())
            .then(json => {
                if (json == true) {
                    location.reload();
                }
            });
    }

    function bindLoadMoreButton() {
        let loadMoreIntval = setInterval(() => {
            let loadMoreWrapper = document.querySelector('.load-more-wrapper');
            if (!!loadMoreWrapper) {
                clearInterval(loadMoreIntval);
                document.querySelector('.button.load-more').addEventListener('click', function () {
                    let jumpIntval = setInterval(() => {
                        if (!loadMoreWrapper.querySelector('.load-more-jump').classList.contains('hidden')) {
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

