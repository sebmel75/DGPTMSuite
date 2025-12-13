(function(){
    function $(sel, ctx){ return (ctx||document).querySelector(sel); }

    async function uploadImage(file, hzId){
        const fd = new FormData();
        fd.append('action','hzb_upload_media_for_hz');
        fd.append('security', HZBUpload.nonce);
        fd.append('herzzentrum_id', hzId);
        fd.append('file', file);
        const res = await fetch(HZBUpload.ajaxUrl, { method:'POST', credentials:'same-origin', body:fd });
        const json = await res.json();
        if(!json || !json.success){ throw new Error(json && json.data ? json.data : 'Upload fehlgeschlagen'); }
        return json.data; // {id,url,thumbnail,medium,filename}
    }

    function connect(field){
        const uploadLink = $('#' + field + '-upload-link');
        const fileInput  = $('#' + field + '-file');
        const previewImg = $('#' + field + '-preview');
        const hiddenId   = $('#' + field);
        const removeLink = $('#' + field + '-remove');

        if(!uploadLink || !fileInput){ return; }

        uploadLink.addEventListener('click', function(e){
            e.preventDefault();
            fileInput.click();
        });

        fileInput.addEventListener('change', async function(e){
            if(!e.target.files || !e.target.files[0]) return;
            const hzId = parseInt($('#herzzentrum_id').value, 10);
            uploadLink.textContent = 'Lade hoch...';
            uploadLink.setAttribute('aria-busy','true');
            try{
                const item = await uploadImage(e.target.files[0], hzId);
                const url = item.medium || item.thumbnail || item.url;
                if(previewImg){ previewImg.src = url; previewImg.style.display = 'inline-block'; }
                if(hiddenId){ hiddenId.value = item.id; }
                if(removeLink){ removeLink.style.display = 'inline'; }
                uploadLink.textContent = 'Erneut hochladen';
            }catch(err){
                alert(err.message || err);
                uploadLink.textContent = 'Bild hochladen';
            }finally{
                uploadLink.removeAttribute('aria-busy');
                fileInput.value = '';
            }
        });

        if(removeLink){
            removeLink.addEventListener('click', function(e){
                e.preventDefault();
                if(previewImg){ previewImg.src = ''; previewImg.style.display = 'none'; }
                if(hiddenId){ hiddenId.value = ''; }
                removeLink.style.display = 'none';
                uploadLink.textContent = 'Bild hochladen';
            });
        }
    }

    // Initialisierung nach DOM-Load und bei dynamischer Formular-Einbindung per AJAX
    document.addEventListener('DOMContentLoaded', function(){
        const observer = new MutationObserver(function(muts){
            muts.forEach(function(m){
                if(m.addedNodes && m.addedNodes.length){
                    if(document.getElementById('hzb-edit-form')){
                        connect('team-bild');
                        connect('klinik-logo');
                    }
                }
            });
        });
        observer.observe(document.body, {childList:true, subtree:true});

        if(document.getElementById('hzb-edit-form')){
            connect('team-bild');
            connect('klinik-logo');
        }
    });
})();
