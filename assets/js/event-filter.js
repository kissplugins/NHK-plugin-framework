(function($){
    $(function(){
        var ajaxHandler = window.nhkAjaxHandler || (window.nhkAjaxHandler = new AjaxErrorHandler());

        $('.nhk-event-filter-form').on('submit', function(e){
            e.preventDefault();
            var form = $(this);
            var container = form.closest('.nhk-events-list');

            var formData = form.serializeArray();
            formData.push({name: 'action', value: 'nhk_event_filter'});
            formData.push({name: 'nonce', value: nhkEventFilter.nonce});

            var params = new URLSearchParams();
            formData.forEach(function(item){
                params.append(item.name, item.value);
            });

            ajaxHandler.makeRequest(nhkEventFilter.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function(response){
                if(response.success && response.data && response.data.html){
                    container.find('.nhk-events-container').html(response.data.html);
                } else {
                    container.find('.nhk-events-container').html('<p>'+nhkEventFilter.strings.noEvents+'</p>');
                }
            }).catch(function(){
                container.find('.nhk-events-container').html('<p>'+nhkEventFilter.strings.error+'</p>');
            });
        });

        $('.clear-filters-button').on('click', function(){
            var form = $(this).closest('form');
            form[0].reset();
            form.trigger('submit');
        });
    });
})(jQuery);
