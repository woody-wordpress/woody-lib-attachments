let replaceAttachment=document.getElementById("replaceAttachment");if(replaceAttachment){let e=document.getElementById("newMediaFrame"),t=document.getElementById("newMediaImg"),n=(document.getElementById("newAttachmentId"),document.getElementById("newFileTitle")),a=document.getElementById("fromToIcon"),c=new URL(window.location.href),d=c.searchParams.get("attachment_id"),i=c.searchParams.get("mime_type");replaceAttachment.addEventListener("click",function(){var c;let d={title:"Remplacer le média",button:{text:"Choisir"},multiple:!1};i&&(d.library={type:i.replace("_","/")}),(c=wp.media.frames.file_frame=wp.media(d)).on("select",function(){attachment=c.state().get("selection").first().toJSON(),t&&t.setAttribute("src",attachment.url),n&&(n.innerHTML=attachment.title),replaceAttachment.classList.add("hidden"),a.classList.remove("hidden"),e.classList.remove("hidden")}),c.open()});let s=document.getElementById("submitNewAttachment");s&&s.addEventListener("click",function(){a.classList.remove("dashicons-arrow-down-alt"),a.classList.add("dashicons-update"),a.classList.add("spin");var t=new Headers;t.append("X-WP-Nonce",wpApiSettings.nonce),fetch("/wp-json/woody/attachments/replace?search="+d+"&replace="+attachment.id,{headers:t}).then(t=>{document.getElementById("woodyMediapageslistTable").innerHTML="<h3>Remplacement en cours - Cette opération peut prendre quelques minutes.<br/>Rafraichissez la page pour afficher une liste à jour</h3>",e.classList.add("hidden")}).catch(e=>{console.error("Replace fetch: "+e)})});let l=document.getElementById("cancelNewAttachment");l&&l.addEventListener("click",function(){e.classList.add("hidden")})}