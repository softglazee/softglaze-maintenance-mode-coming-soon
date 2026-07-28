jQuery(function($){
    
    // 1. Activation Cards (Visual Selection Fix)
    $('.sg-mmcs-radio-card').on('click', function(e){
        // Remove active class from all peers
        $(this).siblings().removeClass('active');
        // Add active class to clicked
        $(this).addClass('active');
        
        // Ensure radio is checked (if clicking the div, not the input)
        var $radio = $(this).find('input[type="radio"]');
        if( ! $(e.target).is('input') ) {
            $radio.prop('checked', true).trigger('change');
        }
    });


    // 1b. Template Preview Cards
    $(document).on('click', '.sg-tmpl-card', function(e){
        var $card = $(this);
        var $grid = $card.closest('.sg-tmpl-grid');
        $grid.find('.sg-tmpl-card').removeClass('active');
        $card.addClass('active');

        var $radio = $card.find('input[type="radio"]');
        if( ! $(e.target).is('input') ) {
            $radio.prop('checked', true).trigger('change');
        }
    });

    
    // 1b-2. Popup Preview for Templates (Thickbox)
    $(document).on('click', '.sg-tmpl-preview-btn', function(e){
        e.preventDefault();
        e.stopPropagation();

        if ( typeof tb_show !== 'function' ) { return; }
        if ( typeof sg_mmcs_data === 'undefined' || ! sg_mmcs_data.preview_base ) { return; }

        var mode = $(this).attr('data-preview-mode') || '';
        var tmpl = $(this).attr('data-preview-template') || '';

        var base = ('' + sg_mmcs_data.preview_base).replace(/#.*$/, '');
        var sep  = base.indexOf('?') === -1 ? '?' : '&';

        var width  = sg_mmcs_data.preview_default_width || 1200;
        var height = sg_mmcs_data.preview_default_height || 760;

        var url = base + sep
            + 'sg_mmcs_preview=1'
            + '&sg_mmcs_preview_mode=' + encodeURIComponent(mode)
            + '&sg_mmcs_preview_template=' + encodeURIComponent(tmpl)
            + '&TB_iframe=true'
            + '&width=' + encodeURIComponent(width)
            + '&height=' + encodeURIComponent(height);

        tb_show('Preview', url);
    });

// 1c. Toggle Password Visibility (Admin)
    $(document).on('click', '.sg-toggle-password', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $input = $btn.closest('.sg-mmcs-row').find('.sg-password-field');
        if(!$input.length) return;

        if($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $btn.text('Hide');
        } else {
            $input.attr('type', 'password');
            $btn.text('Show');
        }
    });


    // 2. Content Presets (Auto-fill with Custom Modal)
    var pendingPresetData = null;

    $('#sg-content-preset').on('change', function(){
        var val = $(this).val();
        if(!val) return;

        var parts = val.split('_'); // e.g. "cs_1" or "mm_1"
        var type = parts[0]; // cs or mm
        var idx = parts[1];  // 1 to 10

        var data = null;
        if( type === 'cs' && sg_mmcs_data.presets_cs && sg_mmcs_data.presets_cs[idx] ) {
            data = sg_mmcs_data.presets_cs[idx];
        } else if( type === 'mm' && sg_mmcs_data.presets_mm && sg_mmcs_data.presets_mm[idx] ) {
            data = sg_mmcs_data.presets_mm[idx];
        }

        if( data ) {
            // Store data and show custom modal instead of browser alert
            pendingPresetData = data;
            // Preview in modal
            $('#sg-preset-preview-head').text(data.head || '');
            $('#sg-preset-preview-msg').text(data.msg || '');
            $('#sg-preset-modal').fadeIn(200);
        } else {
            $(this).val('');
        }
    });

    // Modal Actions
    $('.sg-modal-confirm').on('click', function(e){
        e.preventDefault();
        if( pendingPresetData ) {
             $('#sg-opt-headline').val(pendingPresetData.head);
             $('#sg-opt-message').val(pendingPresetData.msg);
        }
        // Close and Reset
        $('#sg-preset-modal').fadeOut(200);
        $('#sg-content-preset').val('');
        pendingPresetData = null;
    });

    $('.sg-modal-cancel').on('click', function(e){
        e.preventDefault();
        // Close and Reset
        $('#sg-preset-modal').fadeOut(200);
        $('#sg-content-preset').val('');
        pendingPresetData = null;
    });

    // 3. Select All Roles
    $('.sg-select-all-roles').on('click', function(e){
        e.preventDefault();
        var $select = $(this).parent().next('.sg-select-roles');
        $select.find('option').prop('selected', true);
    });

    // 4. Initialize Datepicker
    if( $('.sg-datepicker').length ) {
        $('.sg-datepicker').datepicker({
            dateFormat: 'yy-mm-dd', // Standard DB format
            minDate: 0 // Disable past dates
        });
    }

    // 5. Image Uploader
    $(document).on('click', '.sg-upload-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $input = $btn.siblings('.sg-img-input');
        
        var frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
        });

        frame.open();
    });

    // 6. Repeater Logic (Social Icons & Form Fields)
    
    // Initialize Sortable (Drag & Drop)
    $('.sg-repeater').sortable({
        handle: '.sg-repeater-handle',
        placeholder: 'sg-repeater-placeholder',
        forcePlaceholderSize: true
    });

    // Add Form Field
    $('.sg-add-field-row').on('click', function(e){
        e.preventDefault();
        // Note: The template now includes the "Width" dropdown, so this clone includes it automatically.
        var template = $('#sg-repeater-templates .tmpl-form-field').clone();
        var index = new Date().getTime(); // Unique ID
        var html = template.html().replace(/INDEX/g, index);
        
        var $item = $('<div class="sg-repeater-item"></div>').html(html);
        $('#sg-form-fields').append($item);
    });

    // Add Social Icon
    $('.sg-add-social-row').on('click', function(e){
        e.preventDefault();
        var template = $('#sg-repeater-templates .tmpl-social-icon').clone();
        var index = new Date().getTime();
        var html = template.html().replace(/INDEX/g, index);
        
        var $item = $('<div class="sg-repeater-item"></div>').html(html);
        $('#sg-social-icons').append($item);
    });

    // Remove Row
    $(document).on('click', '.sg-remove-row', function(e){
        e.preventDefault();
        if(confirm('Are you sure?')) {
            $(this).closest('.sg-repeater-item').remove();
        }
    });

    // Auto-fill Field Key based on Label (UX improvement)
    $(document).on('keyup', '.sg-repeater-item input[placeholder="Label"]', function(){
        var val = $(this).val();
        var key = val.toLowerCase().replace(/[^a-z0-9]/g, '_');
        var $keyInput = $(this).closest('.sg-mmcs-row').find('input.small-text');
        // Only auto-fill if key is empty or looks like a slug of the previous label
        if( $keyInput.val() === '' || $keyInput.val().indexOf('_') > -1 ) {
            $keyInput.val(key);
        }
    });

    // Quick Preview Links + Bypass Links
    function sgCopyToClipboard(text) {
        if (!text) { return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function(){
                // Fallback handled below.
            });
            return;
        }
        var $tmp = $('<textarea readonly></textarea>').css({position:'absolute',left:'-9999px'}).val(text);
        $('body').append($tmp);
        $tmp[0].select();
        try { document.execCommand('copy'); } catch(e) {}
        $tmp.remove();
    }

    function sgAjaxPost(action, payload, onOk, onErr){
        if (typeof sg_mmcs_data === 'undefined' || !sg_mmcs_data.ajax_url) {
            if (onErr) onErr('Missing ajax_url');
            return;
        }
        payload = payload || {};
        payload.action = action;
        payload.nonce = sg_mmcs_data.ajax_nonce || '';
        $.post(sg_mmcs_data.ajax_url, payload)
            .done(function(resp){
                if (resp && resp.success) {
                    if (onOk) onOk(resp.data || {});
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Request failed.';
                    if (onErr) onErr(msg);
                }
            })
            .fail(function(){
                if (onErr) onErr('Request failed.');
            });
    }

    $(document).on('click', '.sg-mmcs-generate-preview-link', function(){
        var $wrap = $(this).closest('.sg-mmcs-card-body');
        var mode = $wrap.find('.sg-mmcs-preview-mode').val() || 'maintenance';
        var template = $wrap.find('.sg-mmcs-preview-template').val() || '1';
        var expiry = parseInt($wrap.find('.sg-mmcs-preview-expiry').val(), 10) || 1440;
        var $out = $wrap.find('.sg-mmcs-generated-link');
        var $hint = $wrap.find('.sg-mmcs-link-hint');
        $out.val('');
        $hint.text('');
        sgAjaxPost('sg_mmcs_generate_preview_link', {mode: mode, template: template, expiry_minutes: expiry}, function(data){
            $out.val(data.link || '').trigger('focus');
            if (data.hint) { $hint.text(data.hint); }
        }, function(msg){
            $hint.text(msg);
        });
    });

    $(document).on('click', '.sg-mmcs-copy-link', function(){
        var $wrap = $(this).closest('.sg-mmcs-card-body');
        var val = $wrap.find('.sg-mmcs-generated-link').val() || '';
        sgCopyToClipboard(val);
    });

    $(document).on('click', '.sg-mmcs-generate-bypass-link', function(){
        var $wrap = $(this).closest('.sg-mmcs-card-body');
        var hours = parseInt($wrap.find('.sg-mmcs-bypass-hours').val(), 10) || 8;
        var $out = $wrap.find('.sg-mmcs-bypass-link');
        var $hint = $wrap.find('.sg-mmcs-bypass-hint');
        $out.val('');
        $hint.text('');
        sgAjaxPost('sg_mmcs_generate_bypass_link', {hours: hours}, function(data){
            $out.val(data.link || '').trigger('focus');
            if (data.hint) { $hint.text(data.hint); }
        }, function(msg){
            $hint.text(msg);
        });
    });

    $(document).on('click', '.sg-mmcs-copy-bypass', function(){
        var $wrap = $(this).closest('.sg-mmcs-card-body');
        var val = $wrap.find('.sg-mmcs-bypass-link').val() || '';
        sgCopyToClipboard(val);
    });



// Countdown finish fields toggle
function toggleCountdownFinishFields(){
    var action = $('select[name$="[countdown_action]"]').val() || 'message';
    var $wrap = $('.sg-countdown-finish-fields');
    if(!$wrap.length) return;

    $wrap.toggle(action !== 'hide');
    $wrap.find('.sg-countdown-msg-field').toggle(action === 'message');
    $wrap.find('.sg-countdown-redirect-field').toggle(action === 'redirect');
}
$(document).on('change', 'select[name$="[countdown_action]"]', toggleCountdownFinishFields);
toggleCountdownFinishFields();
});
