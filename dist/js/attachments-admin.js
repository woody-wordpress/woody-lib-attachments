if(document.getElementById("wp-media-grid")){function getGridsAttachments(){let t=setInterval(()=>{let e=document.querySelectorAll(".attachments-browser .attachments .attachment");e.length>0&&(clearInterval(t),e.forEach(t=>{t.addEventListener("click",()=>{document.querySelectorAll(".attachments-browser .attachments .attachment.selected").length>0?(createTermsButton(),bindSecondaryToolbarButtons()):removeTermsButton()})}))},200)}function createTermsButton(){if(!document.getElementById("setTermsButton")){let t=document.querySelector(".media-frame-toolbar .media-toolbar-secondary");termsButton=document.createElement("button"),termsButton.setAttribute("type","button"),termsButton.setAttribute("class","button media-button button-primary button-large"),termsButton.setAttribute("id","setTermsButton"),termsButton.innerHTML="Ajouter des tags aux photos selectionnées",t.prepend(termsButton),termsButton.addEventListener("click",()=>{displayTermsForm()})}}function bindSecondaryToolbarButtons(){let t=document.querySelectorAll(".media-frame-toolbar .media-toolbar-secondary .button");t.length>0&&t.forEach(t=>{"setTermsButton"!=t.getAttribute("id")&&t.addEventListener("click",()=>{removeTermsButton()})})}function removeTermsButton(){let t=document.getElementById("setTermsButton");t&&t.remove()}function displayTermsForm(){let t=document.getElementById("attachmentsAddTerms");t?t.classList.remove("hidden"):(termsForm=document.createElement("div"),termsForm.setAttribute("id","attachmentsAddTerms"),termsForm.innerHTML='<div class="choices"><div><p>Thématiques</p><ul class="themes" id="terms-list"></ul></div><div><p>Lieux</p><ul class="places" id="terms-list"></ul></div><div><p>Circonstances</p><ul class="seasons" id="terms-list"></ul></div></div><div class="actions"><button class="button button-primary apply">Valider</button><button class=" button close">Annuler</button></div>',getAttachmentsTerms(termsForm))}function getAttachmentsTerms(t){var e=new Headers;e.append("X-WP-Nonce",wpApiSettings.nonce),fetch("/wp-json/woody/attachments/terms/get",{headers:e}).then(t=>t.json()).then(e=>{if(e){for(let n in e){let o=t.querySelector("."+n);null!==o&&(o.innerHTML=e[n])}document.getElementById("wpbody-content").append(t);let n=t.querySelectorAll('input[type="checkbox"]');n.length>0&&bindTermsFormActions(t,n)}}).catch(t=>{console.error("Attachments terms fetch: "+t)})}function bindTermsFormActions(t,e){t.querySelector(".button.close").addEventListener("click",()=>{t.classList.add("hidden"),e.forEach(t=>{t.checked=!1})}),t.querySelector(".button.apply").addEventListener("click",()=>{setPostTerms(e)})}function setPostTerms(t){let e=[],n=[],o=document.querySelectorAll(".attachments-browser .attachments .attachment.selected");o.length>0&&o.forEach(t=>{e.push(t.dataset.id)}),t.length>0&&t.forEach(t=>{t.checked&&n.push(t.value)}),e.join(","),n.join(",");var r=new Headers;r.append("X-WP-Nonce",wpApiSettings.nonce),fetch("/wp-json/woody/attachments/terms/set?attach_ids="+e+"&terms_ids="+n,{headers:r}).then(t=>t.json()).then(t=>{1==t&&location.reload()})}function bindLoadMoreButton(){let t=setInterval(()=>{let e=document.querySelector(".load-more-wrapper");e&&(clearInterval(t),document.querySelector(".button.load-more").addEventListener("click",function(){let t=setInterval(()=>{e.querySelector(".load-more-jump").classList.contains("hidden")||(clearInterval(t),getGridsAttachments())},100)}))},200)}getGridsAttachments(),bindLoadMoreButton()}