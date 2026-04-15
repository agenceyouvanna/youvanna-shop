/*!
 * Youvanna Shop - Admin JS
 * Tabs, media pickers, slug generator, repeater (variations), sortable images.
 */
(function($){
    'use strict';

    var slugify = function(str) {
        return String(str).toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/['"]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-+/g, '-');
    };

    // ---- Tabs ----
    function initTabs(root) {
        var $root = $(root);
        $root.on('click', '.yv-tabs__btn', function(e){
            e.preventDefault();
            var target = $(this).data('tab');
            $root.find('.yv-tabs__btn').removeClass('is-active');
            $(this).addClass('is-active');
            $root.find('.yv-tab-panel').removeClass('is-active');
            $root.find('.yv-tab-panel[data-panel="'+target+'"]').addClass('is-active');
            try { history.replaceState(null, '', '#'+target); } catch(e) {}
        });
        var hash = (window.location.hash || '').replace('#','');
        if (hash && $root.find('.yv-tabs__btn[data-tab="'+hash+'"]').length) {
            $root.find('.yv-tabs__btn[data-tab="'+hash+'"]').trigger('click');
        } else {
            var first = $root.find('.yv-tabs__btn').first();
            if (first.length && !$root.find('.yv-tabs__btn.is-active').length) {
                first.trigger('click');
            }
        }
    }

    // ---- Slug auto-generate ----
    function initSlug(root) {
        var $name = $(root).find('[data-slug-source]');
        var $slug = $(root).find('[data-slug-target]');
        if (!$name.length || !$slug.length) return;
        var touched = $slug.val().length > 0;
        $slug.on('input', function(){ touched = true; });
        $name.on('input blur', function(){
            if (!touched) {
                $slug.val(slugify($name.val()));
            }
        });
    }

    // ---- Media picker (single image) ----
    function initImagePickers(root) {
        $(root).find('[data-image-picker]').each(function(){
            var $wrap = $(this);
            var $input = $wrap.find('input[type="hidden"]');
            var $preview = $wrap.find('[data-preview]');
            var $pick = $wrap.find('[data-pick]');
            var $clear = $wrap.find('[data-clear]');
            $pick.on('click', function(e){
                e.preventDefault();
                var frame = wp.media({ title: $pick.data('title') || 'Choisir une image', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    $input.val(a.id);
                    var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                    $preview.html('<img src="'+url+'" alt="">');
                });
                frame.open();
            });
            $clear.on('click', function(e){
                e.preventDefault();
                $input.val('');
                $preview.html($preview.data('empty-label') || 'Aucune image');
            });
        });
    }

    // ---- Gallery picker (multiple images) ----
    function initGalleryPickers(root) {
        $(root).find('[data-gallery-picker]').each(function(){
            var $wrap = $(this);
            var $input = $wrap.find('input[type="hidden"]');
            var $grid = $wrap.find('[data-gallery-grid]');
            var $add = $wrap.find('[data-gallery-add]');
            var render = function(){
                var ids = ($input.val() || '').split(',').filter(Boolean).map(Number);
                $grid.find('.yv-gallery__item').remove();
                ids.forEach(function(id){
                    var existing = $wrap.data('cache-'+id);
                    var src = existing || '';
                    $grid.prepend('<div class="yv-gallery__item" data-id="'+id+'"><img src="'+src+'" alt=""><button type="button" data-remove>&times;</button></div>');
                });
            };
            $add.on('click', function(e){
                e.preventDefault();
                var frame = wp.media({ title: 'Ajouter à la galerie', multiple: 'add', library: { type: 'image' } });
                frame.on('select', function(){
                    var sel = frame.state().get('selection');
                    var ids = ($input.val() || '').split(',').filter(Boolean).map(Number);
                    sel.each(function(att){
                        var j = att.toJSON();
                        if (ids.indexOf(j.id) === -1) {
                            ids.push(j.id);
                            $wrap.data('cache-'+j.id, j.sizes && j.sizes.thumbnail ? j.sizes.thumbnail.url : j.url);
                        }
                    });
                    $input.val(ids.join(','));
                    render();
                });
                frame.open();
            });
            $grid.on('click', '[data-remove]', function(e){
                e.preventDefault();
                var id = $(this).closest('.yv-gallery__item').data('id');
                var ids = ($input.val() || '').split(',').filter(Boolean).map(Number).filter(function(x){ return x !== id; });
                $input.val(ids.join(','));
                render();
            });
        });
    }

    // ---- Repeater (variations) ----
    function initRepeaters(root) {
        $(root).find('[data-repeater]').each(function(){
            var $wrap = $(this);
            var $body = $wrap.find('[data-repeater-body]');
            var $tpl = $wrap.find('[data-repeater-tpl]');
            var $add = $wrap.find('[data-repeater-add]');
            $add.on('click', function(e){
                e.preventDefault();
                var html = $tpl.html().replace(/__INDEX__/g, Date.now() + Math.floor(Math.random()*1000));
                $body.append(html);
            });
            $body.on('click', '[data-repeater-remove]', function(e){
                e.preventDefault();
                $(this).closest('[data-repeater-row]').remove();
            });
        });
    }

    // ---- Star input ----
    function initStarInputs(root) {
        $(root).find('[data-star-input]').each(function(){
            var $wrap = $(this);
            var $input = $wrap.find('input[type="hidden"]');
            var cur = parseInt($input.val(), 10) || 0;
            var render = function(v){
                $wrap.find('button').each(function(i){
                    $(this).toggleClass('is-on', (i+1) <= v);
                });
            };
            $wrap.on('click', 'button', function(e){
                e.preventDefault();
                var v = parseInt($(this).data('value'), 10);
                $input.val(v);
                cur = v;
                render(v);
            });
            render(cur);
        });
    }

    // ---- Confirm delete buttons ----
    function initConfirms(root) {
        $(root).on('click', '[data-confirm]', function(e){
            var msg = $(this).data('confirm') || 'Confirmer ?';
            if (!window.confirm(msg)) { e.preventDefault(); }
        });
    }

    // ---- SEO live preview (Google SERP) ----
    function initSeoPreview(root) {
        $(root).find('[data-seo-preview]').each(function(){
            var $wrap = $(this);
            var fallbackTitle = $wrap.data('fallback-title') || '';
            var fallbackDesc = $wrap.data('fallback-desc') || '';
            var $title = $wrap.find('[data-seo-title]');
            var $desc = $wrap.find('[data-seo-desc]');
            var $titleInput = $(root).find('[data-seo-title-input]');
            var $descInput = $(root).find('[data-seo-desc-input]');
            var $nameInput = $(root).find('[data-slug-source]');
            var $shortInput = $(root).find('[name="short_description"]');

            function truncate(s, n) {
                s = (s || '').toString().trim();
                if (s.length <= n) return s;
                return s.slice(0, n - 3) + '...';
            }

            function currentFallbackTitle() {
                var name = $nameInput.length ? ($nameInput.val() || '').trim() : '';
                if (!name) return fallbackTitle;
                var sep = fallbackTitle.indexOf(' - ');
                var suffix = sep >= 0 ? fallbackTitle.slice(sep) : '';
                return name + suffix;
            }

            function currentFallbackDesc() {
                var short = $shortInput.length ? ($shortInput.val() || '').trim() : '';
                return short ? truncate(short, 155) : fallbackDesc;
            }

            function sync() {
                var t = ($titleInput.val() || '').trim();
                var d = ($descInput.val() || '').trim();
                $title.text(t || currentFallbackTitle());
                $desc.text(d || currentFallbackDesc());
                $titleInput.attr('placeholder', currentFallbackTitle());
                var ph = currentFallbackDesc();
                if (ph) $descInput.attr('placeholder', ph);
            }

            $titleInput.on('input', sync);
            $descInput.on('input', sync);
            $nameInput.on('input', sync);
            $shortInput.on('input', sync);
            sync();
        });
    }

    $(function(){
        var $root = $('.yv-admin');
        if (!$root.length) return;
        initTabs($root);
        initSlug($root);
        initImagePickers($root);
        initGalleryPickers($root);
        initRepeaters($root);
        initStarInputs($root);
        initConfirms($root);
        initSeoPreview($root);
    });

})(jQuery);
