jQuery(function($){

    /* --- 1. COUNTDOWN TIMER --- */
    function initCountdown(){
        var $cd = $('.sg-countdown');
        if(!$cd.length) return;

        var dateStr = $cd.data('date'); // Format: YYYY-MM-DD HH:MM
        if(!dateStr) return;

        // Robust parsing for Safari/mobile compatibility
        // Attempt to replace spaces with 'T' for ISO format
        var targetDate = new Date(dateStr.replace(' ', 'T'));
        
        // Fallback parser if standard fails (Safari often needs this)
        if( isNaN(targetDate.getTime()) ) {
            var parts = dateStr.split(/[- :]/); // Split by dash, space, or colon
            // new Date(year, monthIndex, day, hours, minutes, seconds)
            targetDate = new Date(
                parts[0], 
                (parts[1]-1), 
                parts[2], 
                parts[3]||0, 
                parts[4]||0, 
                parts[5]||0
            );
        }

        if( isNaN(targetDate.getTime()) ) return; // Invalid date, stop.

        function tick(){
            var now = new Date();
            var diff = Math.max(0, targetDate.getTime() - now.getTime());

            // Calculate Time Units
            var s = Math.floor(diff/1000);
            var d = Math.floor(s/86400); s -= d*86400;
            var h = Math.floor(s/3600); s -= h*3600;
            var m = Math.floor(s/60); s -= m*60;

            // Update Text Nodes
            // We use .text() to update the visible numbers
            $cd.find('[data-k="d"]').text(d);
            $cd.find('[data-k="h"]').text(h < 10 ? '0'+h : h);
            $cd.find('[data-k="m"]').text(m < 10 ? '0'+m : m);
            $cd.find('[data-k="s"]').text(s < 10 ? '0'+s : s);

            // Update Glitch Layers (if present)
            // The glitch effect uses duplicate layers for the animation
            $cd.find('.sg-layer-1[data-k="d"], .sg-layer-2[data-k="d"]').text(d);
            $cd.find('.sg-layer-1[data-k="h"], .sg-layer-2[data-k="h"]').text(h < 10 ? '0'+h : h);
            $cd.find('.sg-layer-1[data-k="m"], .sg-layer-2[data-k="m"]').text(m < 10 ? '0'+m : m);
            $cd.find('.sg-layer-1[data-k="s"], .sg-layer-2[data-k="s"]').text(s < 10 ? '0'+s : s);

            // Update SVG Rings (for Circle Layout)
            // Stroke-dasharray is 283 (2 * pi * 45)
            if( $cd.hasClass('sg-cd-circle') ) {
                setRing('d', d, 365); // Assume max 365 days for ring context
                setRing('h', h, 24);
                setRing('m', m, 60);
                setRing('s', s, 60);
            }

            // Finished?
            if( diff <= 0 ) {
                clearInterval(timerInterval);
                handleFinish();
            }
        }

        function setRing(k, val, max) {
            var $r = $cd.find('[data-k="'+k+'-ring"]');
            if(!$r.length) return;
            
            // Calculate offset (Empty = 283, Full = 0)
            // We want it to empty as time goes down
            var offset = 283 * (1 - (val/max)); 
            
            // Clamp values
            if(offset < 0) offset = 0; 
            if(offset > 283) offset = 283;
            
            $r.css('stroke-dashoffset', offset);
        }

        function handleFinish() {
            var action = sg_mmcs_vars.countdown_action || 'message';
            
            if( action === 'hide' ) {
                $cd.fadeOut();
            } 
            else if( action === 'redirect' && sg_mmcs_vars.countdown_redirect ) {
                window.location.href = sg_mmcs_vars.countdown_redirect;
            } 
            else {
                // Default: Show Message
                $cd.hide();
                $('.sg-countdown-msg').fadeIn();
            }
        }

        var timerInterval = setInterval(tick, 1000);
        tick(); // Run immediately on load
    }

    /* --- 2. SUBSCRIBE FORM (Built-in) --- */
    function initSubscribe(){
        var $form = $('.sg-mmcs-form');
        if(!$form.length) return;

        $form.on('submit', function(e){
            e.preventDefault();
            var $msg = $('.sg-mmcs-form-msg');
            var $btn = $form.find('button');
            
            // Reset state
            $msg.removeClass('sg-error').text('Sending...');
            $btn.prop('disabled', true).addClass('sg-loading');

            // Collect all fields automatically (Email, Name, Phone, Custom)
            var formData = {
                action: 'sg_mmcs_subscribe',
                nonce: sg_mmcs_vars.nonce,
                source_url: window.location.href
            };

            // Loop through all inputs (supports dynamic fields)
            $.each($form.serializeArray(), function(i, field){
                formData[field.name] = field.value;
            });

            $.post(sg_mmcs_vars.ajax_url, formData)
                .done(function(res){
                    $btn.prop('disabled', false).removeClass('sg-loading');
                    if(res && res.success){
                        // Success
                        $msg.text(res.data && res.data.message ? res.data.message : 'Thanks!');
                        $form.find('input').val(''); // Clear inputs
                        $form.hide(); // Hide form for cleaner look
                        $msg.fadeIn();
                    } else {
                        // Error
                        $msg.addClass('sg-error').text((res && res.data && res.data.message) ? res.data.message : 'Error.');
                    }
                })
                .fail(function(xhr){
                    $btn.prop('disabled', false).removeClass('sg-loading');
                    var res = xhr.responseJSON;
                    $msg.addClass('sg-error').text((res && res.data && res.data.message) ? res.data.message : 'Server error. Try again.');
                });
        });
    }

    /* --- 3. ACCESS MODAL --- */
    function initModal(){
        var $modal = $('#sg-access-modal');
        var $triggers = $('.sg-access-trigger');
        var $close = $('.sg-modal-close');

        if(!$modal.length) return;

        // Open
        $triggers.on('click', function(e){
            e.preventDefault();
            $modal.addClass('active');
            setTimeout(function(){
                $modal.find('input[type="password"]').focus();
            }, 100);
        });

        // Close
        $close.on('click', function(e){
            e.preventDefault();
            $modal.removeClass('active');
        });

        // Close on outside click
        $modal.on('click', function(e){
            if( $(e.target).is($modal) ) {
                $modal.removeClass('active');
            }
        });
        
        // Close on Escape key
        $(document).on('keyup', function(e){
            if(e.key === "Escape" && $modal.hasClass('active')) {
                $modal.removeClass('active');
            }
        });
    }

    // Initialize All
    initCountdown();
    initSubscribe();
    initModal();
});