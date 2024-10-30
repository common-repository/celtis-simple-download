
(function () {    
    const { unfiltered, L_dlgtitle, L_save, L_exit, L_inpnotice  } = window.extopt;    
    
    let gpost_id = 0;
    
    CELTISDL_validinput = function(inputdata) {
        // 0-9、A-Z、a-z、_、-以外の文字を削除
        inputdata.value = inputdata.value.replace(/[^0-9A-Za-z_\-]/g, '');        
    };
    
    CELTISDL_post_edit = function( post_id, nonce, escjsondt ){
        jQuery( "#celtisdl-post-edit-dialog" ).dialog({
            dialogClass : 'wp-dialog',
            modal       : true,
            autoOpen    : true,
            draggable   : false,
            height      : 'auto',
            width       : '80vw',
            buttons :
            [{
                text: L_save,
                click: function() {
                    const post_title    = document.querySelector('input[name=post_title]').value;
                    const attach_id     = document.querySelector('input[name=celtisdl_attach_id]').value;
                    const post_stat     = document.querySelector('input[name=p-mode]:checked').value;
                    const post_password = document.querySelector('input[name=p-password]').value;
                    CELTISDL_regist_file( post_id, post_title, attach_id, post_stat, post_password, nonce );                    
                }
            },
            {
                text: L_exit,
                id: "cancelButton",
                click: function() {
                    jQuery('#celtisdl-post-edit-dialog').dialog('close');  
                }
            }],          
            open: function() {
                const form = document.querySelector('#celtisdl-post-edit-form');
                if(form){
                    gpost_id = post_id;
                    let post_title      = '';
                    let post_excerpt    = '';
                    let attach_id       = 0;
                    let attach_parent   = 0;
                    let attach_mime     = '';
                    let attach_icon     = '';
                    let file_thumb      = '';
                    let attach_name     = '';
                    let file_date       = '';
                    let attach_fmtsize  = '';
                    let attach_imgratio = '';
                    let post_password   = '';
                    let pwskip_capa     = '';
                    form.removeAttribute( "input-error" );

                    if( escjsondt != '' ){
                        const postdt = JSON.parse(escjsondt);    
                        post_title      = postdt.post_title;
                        post_excerpt    = postdt.post_excerpt;
                        attach_id       = postdt.attach_id;
                        attach_mime     = postdt.attach_mime;
                        attach_icon     = postdt.attach_icon;
                        file_thumb      = '<img src="' + postdt.attach_icon + '" class="icon" alt="">';
                        attach_name     = postdt.attach_name;
                        file_date       = postdt.file_date;
                        attach_fmtsize  = postdt.attach_fmtsize;
                        attach_imgratio = postdt.attach_imgratio;
                        const rbsel = document.querySelectorAll('input[name=p-mode]');
                        if(postdt.post_status == 'publish'){
                            rbsel[1].checked = true; 
                        } else {
                            rbsel[0].checked = true;                             
                        }
                        post_password   = postdt.post_password;
                        pwskip_capa     = postdt.pwskip_capa;
                        
                    }
                    document.querySelector('input[name=post_title]').value          = post_title;
                    document.querySelector('input[name=post_excerpt]').value        = post_excerpt;
                    document.querySelector('input[name=celtisdl_attach_id]').value  = attach_id;
                    document.querySelector('input[name=celtisdl_attach_mime]').value= attach_mime;
                    document.querySelector('input[name=celtisdl_attach_icon]').value= attach_icon;
                    document.getElementById('celtisdl_file_thumb').innerHTML        = file_thumb;
                    document.getElementById('celtisdl_file_name').textContent       = attach_name;
                    document.getElementById('celtisdl_file_date').textContent       = file_date;
                    document.getElementById('celtisdl_file_size').textContent       = attach_fmtsize;
                    document.getElementById('celtisdl_img_ratio').textContent       = attach_imgratio;                        
                    document.querySelector('input[name=p-password]').value          = post_password;
                    document.querySelector('input[name=celtisdl_pwskip_capa]').value= pwskip_capa;                    
                    document.getElementById("cancelButton").focus();
                }                
            },             
            close: function() {
            }            
        });
    };

    CELTISDL_op_update = function( nonce ) {
        let params = new URLSearchParams();
        params.append('action', 'celtisdl_op_update');
        params.append('rewrite_slug', document.querySelector('input[name=celtisdl-rewrite-slug]').value);
        params.append('unfiltered_upload', document.querySelector('input[name=celtisdl-unfiltered-upload]').checked);
        params.append('pwskip_refurl', document.querySelector('textarea[name=celtisdl-pwskip-refurl]').value);
        params.append('prevent_dl_domain', document.querySelector('textarea[name=celtisdl-prevent-dl-domain]').value);
        params.append('_ajax_nonce', nonce);
        params.append('_ajax_plf', 'celtis-simple-download');
        fetch( ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
        })
        .then(function(response) {
            if(response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok.');     
        })
        .then(function(response) {
            if (response.success == true) {
                document.querySelector('input[name=celtisdl-rewrite-slug]').value        = response.rewrite_slug;
                document.querySelector('input[name=celtisdl-unfiltered-upload]').checked = response.unfiltered_upload;
                document.querySelector('textarea[name=celtisdl-pwskip-refurl]').value    = response.pwskip_refurl;
                document.querySelector('textarea[name=celtisdl-prevent-dl-domain]').value= response.prevent_dl_domain;
                if (response.reload == true) {
                    location.reload();
                }
            } else {
                alert(response.msg);
            }
        })
        .catch(function(error) { //alert("ajax error");
        });
    };

    CELTISDL_regist_file = function( post_id, title, attach_id, stat, password, nonce ) {
        const form = document.querySelector('#celtisdl-post-edit-form');
        if(form){
            if(!title || !attach_id || attach_id == 0 || !stat || !password ){
                form.setAttribute( "input-error", "" );
                form.setAttribute( "data-notice", L_inpnotice );    
            } else {
                form.removeAttribute( "input-error" );
                let params = new URLSearchParams();
                params.append('action', 'celtisdl_regist_file');
                params.append('post_id', post_id);
                params.append('post_title', title);
                params.append('attach_id',  attach_id);
                params.append('post_status', stat);
                params.append('post_password', password);
                params.append('post_excerpt',   document.querySelector('input[name=post_excerpt]').value);
                params.append('attach_fmtsize', document.getElementById('celtisdl_file_size').textContent);
                params.append('attach_imgratio',document.getElementById('celtisdl_img_ratio').textContent);
                params.append('attach_mime', document.querySelector('input[name=celtisdl_attach_mime]').value);
                params.append('attach_icon', document.querySelector('input[name=celtisdl_attach_icon]').value);
                params.append('pwskip_capa', document.querySelector('input[name=celtisdl_pwskip_capa]').value);
                params.append('_ajax_nonce', nonce);
                params.append('_ajax_plf', 'celtis-simple-download');
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                })
                .then(function(response) {
                    console.log(response);
                    if(response.ok) {
                        return response.json();
                    }
                    throw new Error('Network response was not ok.');                    
                })
                .then(function(response) {
                    if (response.pid !== 0) {
                        location.reload();
                    } else {
                        alert(response.msg);
                    }
                })
                .catch(function(error) { //alert("ajax error");
                });                
            }
        }             
        return;
    };
    
    CELTISDL_add_new = function( nonce ) {
        let params = new URLSearchParams();
        params.append('action', 'celtisdl_add_new');
        params.append('_ajax_nonce', nonce);
        params.append('_ajax_plf', 'celtis-simple-download');
        fetch( ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
        })
        .then(function(response) {
            if(response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok.');     
        })
        .then(function(response) {
            if (response.pid !== 0) {
                CELTISDL_post_edit(response.pid, nonce, '');
            } else {
                alert(response.msg);
            }
        })
        .catch(function(error) { //alert("ajax error");
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Prioritize display of “Upload File” tab from “Media Library”
        // https://web.contempo.jp/weblog/tips/post-1975
        wp.media.controller.Library.prototype.defaults.contentUserSetting=false;
        
        /**
         * Create a new MediaLibraryUploadedFilter.
         */
        //https://atimmer.github.io/wordpress-jsdoc/media_views_attachment-filters.js.html
        //https://cobbledco.de/adding-your-own-filter-to-the-media-uploader/
        var MediaLibraryUploadedFilter = wp.media.view.AttachmentFilters.extend({

            id: 'media-attachment-uploaded-filter',
            
            initialize: function() {
                this.createFilters();
                _.extend( this.filters, this.options.filters );

                // Build `<option>` elements.
                this.$el.html( _.chain( this.filters ).map( function( filter, value ) {
                    return {
                        el: jQuery( '<option></option>' ).val( value ).html( filter.text )[0],
                        priority: filter.priority || 50
                    };
                }, this ).sortBy('priority').pluck('el').value() );

                this.$el.val( 'uploaded' );
                var filter = this.filters[ this.el.value ];
                this.model.set( filter.props );
            },
            createFilters: function() {
                var filters = {};
                filters.uploaded = {
                    text:  wp.media.view.l10n.uploadedToThisPost,
                    props: {
                        status:  null,
                        type:    null,
                        uploadedTo: gpost_id,
                        orderby: 'date',
                        order:   'DESC'
                    },
                    priority: 10
                };
                filters.unattached = {
                    text:  wp.media.view.l10n.unattached,
                    props: {
                        status:  null,
                        type:    null,
                        uploadedTo: 0,
                        orderby: 'date',
                        order:   'DESC'                        
                    },
                    priority: 20
                };                
                filters.all = {
                    text:  wp.media.view.l10n.allMediaItems,
                    props: {
                        status:  null,
                        type:    null,
                        uploadedTo: null,
                        orderby: 'date',
                        order:   'DESC'
                    },
                    priority: 30
                };
                this.filters = filters;
            }       
        });

        /**
         * Extend and override wp.media.view.AttachmentsBrowser
         * to include our new filter
         */
        var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
        wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
            createToolbar: function() {
                // Make sure to load the original toolbar
                AttachmentsBrowser.prototype.createToolbar.call( this );                
                this.toolbar.set(
                    'MediaLibraryUploadedFilter',
                    new MediaLibraryUploadedFilter({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority:   -100
                    })
                    .render()         
                );
            }
        });                   
        
        //Media Library : file select button click 
        document.addEventListener('click', function(e) {
          if (e.target && e.target.id === 'celtisdl_uploader_button') {                
            e.preventDefault();
          
            const select_file_states = [
                // Medialibrary select filter
                new wp.media.controller.Library( {
                    title: L_dlgtitle,
                    multiple: false,
                    //filterable: 'all',
                    searchable: true,
                    library: wp.media.query(),                 
                } )
            ];

            const celtisdl_file_frame = wp.media({
                multiple: false,
                states: select_file_states,
            });               

            // Add data such as post ID to upload-attachment ajax
            celtisdl_file_frame.uploader.options.uploader.params = { 
                post_id: gpost_id,
                type: 'cs_download'
            };

            //jQuery dialog nest - Hide parent dialog because input does not have focus
            celtisdl_file_frame.on('open', function () {
                document.getElementById("celtisdl-post-edit-dialog").style.display = "none";
                if(unfiltered){
                    //unfiltered_upload : mime_types filter disable
                    window.plupload.addFileFilter('mime_types', function(filters, file, cb) {
                        cb(true);
                    });                    
                }              
            });
            celtisdl_file_frame.on('close', function () {
                document.getElementById("celtisdl-post-edit-dialog").style.display = "block";
                document.getElementById("cancelButton").focus();
            });

            celtisdl_file_frame.open();
            
            //Show selected file information
            celtisdl_file_frame.on("select", function () {
                const attachment = celtisdl_file_frame.state().get('selection').first().toJSON();
                
                let iconsrc = attachment.icon;
                let ratio = '';
                if(attachment.type === 'image'){
                    if(attachment.sizes.thumbnail.url){
                        iconsrc = attachment.sizes.thumbnail.url; 
                        const imgsize = 'width="' + attachment.sizes.thumbnail.width + '" height="' + attachment.sizes.thumbnail.height + '"';
                        const thumb = '<img src="' + iconsrc + '" ' + imgsize + ' class="icon" alt="">';
                        document.getElementById('celtisdl_file_thumb').innerHTML  = thumb;
                    }
                    ratio = attachment.sizes.full.width + ' x ' + attachment.sizes.full.height;
                } else {
                    //icon:"/wp-includes/images/media/archive.png"
                    const thumb = '<img src="' + iconsrc + '" class="icon" alt="">';
                    document.getElementById('celtisdl_file_thumb').innerHTML  = thumb;
                }
                document.getElementById('celtisdl_attach_id').value = attachment.id;
                if (attachment.type === 'image' && attachment.originalImageName) {
                    document.getElementById('celtisdl_file_name').textContent = attachment.originalImageName;
                } else {
                    document.getElementById('celtisdl_file_name').textContent = attachment.filename;
                }
                document.getElementById('celtisdl_file_date').textContent = attachment.dateFormatted;
                document.getElementById('celtisdl_file_size').textContent = attachment.filesizeHumanReadable;
                document.getElementById('celtisdl_img_ratio').textContent = ratio;
                document.getElementById('celtisdl_attach_mime').value = attachment.mime;
                document.getElementById('celtisdl_attach_icon').value = iconsrc;
            });
          }
        });
    });    
}());