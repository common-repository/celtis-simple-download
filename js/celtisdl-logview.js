
(function () {
    Set_filter_date = function(s_date, e_date){
        document.querySelector('input[name=celtisdl_log_start]').value = s_date;
        document.querySelector('input[name=celtisdl_log_end]').value   = e_date;
    };  
    
    Apply_celtisdl_log_nav = function(nonce, button){
        let log_page = parseInt(document.querySelector('#celtisdl-log-page').value, 10);
        if(button == 'next'){
            log_page += 1;
        } else if(button == 'prev'){
            log_page -= 1;
        } else {
            log_page = 0;
        }
        if(log_page >= 0){
            if(! document.querySelector('a.button.ajax-submit').getAttribute('disabled')){
                document.querySelectorAll('a.button.ajax-submit').forEach((function(el){ el.setAttribute('disabled', true);}));
            }
            let params = new URLSearchParams();
            params.append("action", "celtisdl_log_nav");
            params.append("log_page", log_page);
            params.append("s_date", document.querySelector('input[name=celtisdl_log_start]').value);
            params.append("e_date", document.querySelector('input[name=celtisdl_log_end]').value);
            params.append("_ajax_nonce", nonce);
            params.append('_ajax_plf', 'celtis-simple-download');
            fetch( ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            })                
            .then( function(response){
                if(response.ok) {
                    return response.json();
                }
                throw new Error('Network response was not ok.');
            })
            .then( function(json) {
                document.querySelectorAll('a.button.ajax-submit').forEach((function(el){ el.removeAttribute('disabled');}));
                if(json.log !== ''){
                    document.querySelector('#celtisdl-stat').innerHTML = json.stat;
                    document.querySelector('#celtisdl-log').innerHTML  = json.log;
                    document.querySelector('#celtisdl-log-page').value = json.page;
                    setTimeout(function(){ document.querySelector('#celtisdl-log').scrollTo(0, 0);}, 10); 
                } else { alert( json.msg ); }
            })                    
            .catch( function(error){
                document.querySelectorAll('a.button.ajax-submit').forEach((function(el){ el.removeAttribute('disabled');}));
            })        
        }
    };      
}());
