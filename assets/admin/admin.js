( function ( DSK ) {
	'use strict';

	// ── Config ────────────────────────────────────────────────────────────────

	var cfg     = window.DSK_ADMIN || {};
	var nonce   = cfg.nonce   || '';
	var ajaxurl = cfg.ajaxurl || window.ajaxurl || ( window.location.origin + '/wp-admin/admin-ajax.php' );


	// ── Ajax ──────────────────────────────────────────────────────────────────

	DSK.ajax = function ( action, data, options ) {
    	var body = new URLSearchParams( data || {} );
    	body.set( 'action', action );
    	body.set( 'nonce',  nonce );
    
    	return fetch( ajaxurl, {
    		method:      'POST',
    		credentials: 'same-origin',
    		headers:     { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    		body:        body.toString(),
    		signal:      options && options.signal ? options.signal : undefined,
    	} )
    	.then( function ( r ) {
    		return r.json().catch( function () {
    			throw new Error( r.ok ? 'Invalid JSON response' : 'HTTP ' + r.status );
    		} );
    	} )
    	.then( function ( res ) {
    		if ( ! res || res.success !== true ) {
    			var message = res && res.data && res.data.message
    				? res.data.message
    				: 'Request failed';
    			throw new Error( message );
    		}
    		return res.data;
    	} );
    };


	// ── Busy state ────────────────────────────────────────────────────────────

	DSK.setBusy = function ( busy, btn, labels ) {
		btn = btn || document.querySelector( '.dsk-submit' );
		if ( ! btn ) return;

		labels = labels || {};

		btn.disabled    = !! busy;
		btn.textContent = busy ? ( labels.busy || 'Saving\u2026' ) : ( labels.idle || 'Save' );
		btn.classList.toggle( 'is-busy', !! busy );
	};


	// ── Collapsibles ──────────────────────────────────────────────────────────

	function getNaturalHeight( panel ) {
		var prev          = panel.style.maxHeight;
		panel.style.maxHeight = 'none';
		var h             = panel.scrollHeight;
		panel.style.maxHeight = prev;
		return h;
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.dsk-toggle[aria-controls]' );
		if ( ! btn ) return;

		var id    = btn.getAttribute( 'aria-controls' );
		var panel = document.getElementById( id );
		if ( ! panel ) return;

		var open = btn.getAttribute( 'aria-expanded' ) === 'true';

		if ( ! open ) {
			var h = getNaturalHeight( panel );
			btn.setAttribute( 'aria-expanded', 'true' );
			panel.classList.add( 'is-open' );
			panel.style.maxHeight = '0px';
			requestAnimationFrame( function () {
				panel.style.maxHeight = ( h + 16 ) + 'px';
			} );
		} else {
			btn.setAttribute( 'aria-expanded', 'false' );
			panel.classList.remove( 'is-open' );
			panel.style.maxHeight = panel.scrollHeight + 'px';
			requestAnimationFrame( function () {
				panel.style.maxHeight = '0px';
			} );
		}
	} );

	window.DSK_refreshCollapsible = function ( panelId ) {
		var panel = document.getElementById( panelId );
		if ( ! panel || panel.style.maxHeight === '0px' ) return;
		panel.style.maxHeight = 'none';
		var h = panel.scrollHeight;
		panel.style.maxHeight = ( h + 16 ) + 'px';
	};


	// ── Subtabs helper ────────────────────────────────────────────────────────
	//
	// DSK.initSubtabs( options )
	//
	// options:
	//   chips       {string}  Selector for the chip buttons.             Required.
	//   panels      {string}  Selector for the panel elements.           Required.
	//   key         {string}  Attribute on chips that holds the value.   Default: 'data-tab'
	//   panelKey    {string}  Attribute on panels that holds the value.  Default: 'data-panel'
	//   panelPrefix {string}  Prepended to chip value when matching by id. Default: ''
	//   onChange    {fn}      Called with (value) after a tab switches.  Optional.
	//
	// The chip attribute value must match the panel attribute value.
	// Inactive panels are hidden via display:none so they play nicely with
	// both CSS-class-driven layouts and the dsk-subtab-panel flex pattern.

	DSK.initSubtabs = function ( options ) {
		var chips    = document.querySelectorAll( options.chips );
		var panels   = document.querySelectorAll( options.panels );
		var key      = options.key      || 'data-tab';
		var panelKey = options.panelKey || 'data-panel';

		if ( ! chips.length ) return;

		function activate( value ) {
			chips.forEach( function ( c ) {
				c.classList.toggle( 'is-active', c.getAttribute( key ) === value );
			} );
			panels.forEach( function ( p ) {
				// Support matching by attribute (data-panel) or by id (with optional prefix)
				var match = panelKey === 'id'
					? p.id === ( options.panelPrefix || '' ) + value
					: p.getAttribute( panelKey ) === value;
				p.classList.toggle( 'is-active', match );
				p.style.display = match ? '' : 'none';
			} );
			if ( options.onChange ) options.onChange( value );
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				activate( this.getAttribute( key ) );
			} );
		} );

		// Activate the chip that is already marked is-active, or the first one
		var initial = document.querySelector( options.chips + '.is-active' );
		if ( ! initial ) initial = chips[ 0 ];
		if ( initial ) activate( initial.getAttribute( key ) );

		// Return activate() so the caller can programmatically switch tabs
		return activate;
	};


	// ── Shell / tab routing ───────────────────────────────────────────────────

	var DEFAULT_TAB = DSK_SHELL.defaultTab;
	var PAGE_SLUG   = DSK_SHELL.pageSlug;

	var wrapper   = document.querySelector( '.dsk-tab-content' );
	var _tabAbort = null;

	function getActiveTab() {
		var params = new URLSearchParams( window.location.hash.replace( '#', '' ) );
		return params.get( 'tab' ) || DEFAULT_TAB;
	}

	function setActiveTab() {
		var tab = getActiveTab();
		document.querySelectorAll( '.dsk-tab' ).forEach( function ( el ) {
			el.classList.toggle( 'is-active', el.getAttribute( 'data-tab' ) === tab );
		} );
	}

	function highlightWpMenu() {
		var tab   = getActiveTab();
		var hash  = window.location.hash;
		var items = document.querySelectorAll(
			'#adminmenu .toplevel_page_' + PAGE_SLUG + ' ul.wp-submenu li'
		);

		items.forEach( function ( li ) { li.classList.remove( 'current' ); } );

		if ( ! hash || hash === '#' || tab === DEFAULT_TAB ) {
			var home = document.querySelector(
				'#adminmenu .toplevel_page_' + PAGE_SLUG +
				' ul.wp-submenu li a[href="admin.php?page=' + PAGE_SLUG + '"]'
			);
			if ( home ) home.parentElement.classList.add( 'current' );
			return;
		}

		items.forEach( function ( li ) {
			var a = li.querySelector( 'a' );
			if ( a && ( a.getAttribute( 'href' ) || '' ).endsWith( hash ) ) {
				li.classList.add( 'current' );
			}
		} );
	}

	function loadTabContent() {
        if ( _tabAbort ) { _tabAbort.abort(); }
        _tabAbort = new AbortController();
        var signal = _tabAbort.signal;
    
        wrapper.classList.add( 'is-loading' );
    
        if ( ! wrapper.querySelector( '.dsk-loading' ) ) {
            wrapper.innerHTML = '<div class="dsk-loading"><span></span><span></span><span></span></div>';
        }
    
        DSK.ajax( 'dsk_load_tab', { tab: getActiveTab() }, { signal: signal } )
            .then( function ( data ) {
                if ( ! data || ! data.html ) {
                    wrapper.innerHTML = '<h1>Error</h1><p>No content returned.</p>';
                    return;
                }
    
                wrapper.innerHTML = data.html;
                wrapper.classList.remove( 'is-ready' );
                // is-ready is now set by print_tab_assets after module init runs.
    
                wrapper.querySelectorAll( 'script' ).forEach( function ( oldScript ) {
                    var s = document.createElement( 'script' );
                    s.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild( s, oldScript );
                } );
    
                DSK.initTab( getActiveTab() );
            } )
            .catch( function ( err ) {
                if ( err.name === 'AbortError' ) return;
                wrapper.innerHTML = '<h1>Error</h1><p>Failed to load tab content.</p>';
            } )
            .then( function () {
                wrapper.classList.remove( 'is-loading' );
                _tabAbort = null;
            } );
    }

	document.querySelectorAll( '.dsk-tab' ).forEach( function ( el ) {
		el.addEventListener( 'click', function ( e ) {
			var href = this.getAttribute( 'href' ) || '';
			if ( href.includes( '#' ) ) {
				e.preventDefault();
				window.location.hash = href.split( '#' )[ 1 ];
			}
		} );
	} );

	var dashLink = document.querySelector(
		'#adminmenu a[href="admin.php?page=' + PAGE_SLUG + '"]'
	);

	if ( dashLink ) {
		dashLink.addEventListener( 'click', function ( e ) {
			if ( window.location.search.includes( 'page=' + PAGE_SLUG ) ) {
				e.preventDefault();
				window.location.hash = 'tab=' + DEFAULT_TAB;
			}
		} );
	}

	window.addEventListener( 'hashchange', function () {
		setActiveTab();
		highlightWpMenu();
		loadTabContent();
	} );

	setActiveTab();
	highlightWpMenu();
	loadTabContent();


	// ── Sidebar collapse ──────────────────────────────────────────────────────

	var aside       = document.querySelector( '.dsk-admin-aside' );
	var toggleBtn   = document.getElementById( 'dsk-aside-toggle' );
	var STORAGE_KEY = 'dsk_aside_collapsed';

	if ( aside && toggleBtn ) {
		// Collapsed class already applied by app-shell.php — just wire the toggle
		toggleBtn.addEventListener( 'click', function () {
			var collapsed = aside.classList.toggle( 'is-collapsed' );
			dskRoot.classList.toggle( 'is-dsk-aside-collapsed', collapsed );
			localStorage.setItem( STORAGE_KEY, collapsed ? '1' : '0' );
		} );
	}


	// ── Dark mode ─────────────────────────────────────────────────────────────

	var THEME_KEY = 'dsk_theme';
	var dskRoot   = document.documentElement;

	DSK.setTheme = function ( dark ) {
		if ( ! dskRoot ) return;

		dskRoot.classList.toggle( 'is-dsk-dark', !! dark );
		localStorage.setItem( THEME_KEY, dark ? 'dark' : 'light' );

		document.querySelectorAll( '.dsk-theme-toggle' ).forEach( function ( btn ) {
			var icon  = btn.querySelector( '.dashicons' );
			var label = btn.querySelector( '.dsk-theme-toggle-label' );

			if ( icon )  icon.className    = 'dashicons ' + ( dark ? 'dashicons-sun' : 'dashicons-moon' );
			if ( label ) label.textContent = dark ? 'Light mode' : 'Dark mode';

			btn.setAttribute( 'aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode' );
		} );

		var cb = document.querySelector( '[data-setting="switch-light-dark-mode"]' );
		if ( cb ) cb.checked = !! dark;
	};

	// Theme class already applied by app-shell.php — just sync toggle UI.
	DSK.setTheme( dskRoot.classList.contains( 'is-dsk-dark' ) );

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '[data-setting="switch-light-dark-mode"]' ) ) return;
		DSK.setTheme( !! e.target.checked );
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.dsk-theme-toggle' );
		if ( ! btn ) return;
		DSK.setTheme( ! dskRoot.classList.contains( 'is-dsk-dark' ) );
	} );


	// ── Notice system ────────────────────────────────────────────────────────
	//
	// DSK.addNotice( message, options )
	//
	// options:
	//   tab   {string}  data-tab key to badge the nav icon. Optional.
	//   type  {string}  'info' | 'success' | 'warning'. Default: 'info'
	//
	// Notices render above the tab content, full-width, stacked.
	// Dismissing removes the notice for the page session.

	DSK._notices = [];

	DSK.addNotice = function ( message, options ) {
		options = options || {};
		var notice = {
			id:      Math.random().toString(36).slice(2),
			message: message,
			tab:     options.tab  || null,
			type:    options.type || 'info',
		};
		DSK._notices.push( notice );
		DSK._renderNotices();
		if ( notice.tab ) DSK._badgeTab( notice.tab, true );
	};

	DSK._removeNotice = function ( id ) {
		var removed = null;
		DSK._notices = DSK._notices.filter( function ( n ) {
			if ( n.id === id ) { removed = n; return false; }
			return true;
		} );
		DSK._renderNotices();
		if ( removed && removed.tab ) {
			var stillBadged = DSK._notices.some( function ( n ) { return n.tab === removed.tab; } );
			DSK._badgeTab( removed.tab, stillBadged );
		}
	};

	DSK._badgeTab = function ( tabKey, on ) {
		var el = document.querySelector( '.dsk-tab[data-tab="' + tabKey + '"]' );
		if ( el ) el.classList.toggle( 'has-notice', !! on );
	};

	DSK._renderNotices = function () {
		var container = document.getElementById( 'dsk-notices' );
		if ( ! container ) return;
		container.innerHTML = '';
		DSK._notices.forEach( function ( notice ) {
			var el = document.createElement( 'div' );
			el.className = 'dsk-notice dsk-notice--' + notice.type;

			var text = document.createElement( 'span' );
			text.className   = 'dsk-notice-message';
			text.textContent = notice.message;
			el.appendChild( text );

			if ( notice.tab ) {
				var link = document.createElement( 'a' );
				link.className   = 'dsk-notice-tab-link';
				link.textContent = 'Go to ' + notice.tab;
				link.href        = '#tab=' + notice.tab;
				el.appendChild( link );
			}

			var btn = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'dsk-notice-close';
			btn.setAttribute( 'aria-label', 'Dismiss' );
			btn.innerHTML = '&times;';
			( function( id ) {
				btn.addEventListener( 'click', function () { DSK._removeNotice( id ); } );
			} )( notice.id );
			el.appendChild( btn );

			container.appendChild( el );
		} );
	};

	// Drain queue populated by modules via window.DSK_NOTICES before DSK loaded
	( window.DSK_NOTICES || [] ).forEach( function ( n ) {
		DSK.addNotice( n.message, { tab: n.tab, type: n.type } );
	} );
	window.DSK_NOTICES = { push: function( n ) { DSK.addNotice( n.message, { tab: n.tab, type: n.type } ); } };


	// ── Tab initialisers ──────────────────────────────────────────────────────
	// Called by loadTabContent() after the HTML is injected.

	DSK.initTab = function ( tab ) {
		if ( tab === 'dashboard' ) DSK.initDashboard();
		if ( tab === 'settings'  ) DSK.initSettings();
	};


	// ── Dashboard tab ─────────────────────────────────────────────────────────

	DSK.initDashboard = function () {
        var btn = document.querySelector( '.dsk-submit' );
        if ( ! btn ) return;
    
        var listActive   = document.getElementById( 'dsk-modules-active' );
        var listInactive = document.getElementById( 'dsk-modules-inactive' );
    
        // Tracks pending changes: slug → 'activate' | 'deactivate'
        var pending = {};
    
        function syncSaveBtn() {
            btn.disabled = Object.keys( pending ).length === 0;
        }
    
        if ( listActive && listInactive ) {
            // ── Subtab switching via helper ───────────────────────────────────
            DSK.initSubtabs( {
                chips:       '.dsk-dashboard-subtabs .dsk-subtab-chip',
                panels:      '#dsk-modules-active, #dsk-modules-inactive',
                key:         'data-view',
                panelKey:    'id',
                panelPrefix: 'dsk-modules-',
            } );
    
            // ── Toggle ────────────────────────────────────────────────────────
            document.querySelector( '.dsk-tab-inner' ).addEventListener( 'change', function ( e ) {
                var input = e.target;
                if ( ! input || input.type !== 'checkbox' ) return;
    
                var row = input.closest( '.dsk-module-row' );
                if ( ! row ) return;
    
                var slug = row.getAttribute( 'data-slug' );
    
                if ( pending[ slug ] ) {
                    delete pending[ slug ];
                    row.classList.remove( 'is-pending' );
                } else {
                    pending[ slug ] = input.checked ? 'activate' : 'deactivate';
                    row.classList.add( 'is-pending' );
                }
    
                syncSaveBtn();
            } );
    
            // ── Save ──────────────────────────────────────────────────────────
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
    
                var toActivate   = [];
                var toDeactivate = [];
    
                Object.keys( pending ).forEach( function ( slug ) {
                    if ( pending[ slug ] === 'activate' )   toActivate.push( slug );
                    if ( pending[ slug ] === 'deactivate' ) toDeactivate.push( slug );
                } );
    
                if ( ! toActivate.length && ! toDeactivate.length ) return;
    
                DSK.setBusy( true, btn );
    
                DSK.ajax( 'dsk_save_modules', {
                    activate:   JSON.stringify( toActivate ),
                    deactivate: JSON.stringify( toDeactivate ),
                } )
                .then( function () { window.location.reload(); } )
                .catch( function ( err ) {
                    DSK.setBusy( false, btn );
                    btn.disabled = false;
                    alert( err.message );
                } );
            } );
        }
    
        // ── Module info modal — always wired ──────────────────────────────────
        function getOrCreateInfoModal() {
            var overlay = document.getElementById( 'dsk-module-info-modal-overlay' );
    
            if ( overlay ) {
                return {
                    overlay: overlay,
                    title:   document.getElementById( 'dsk-module-info-title' ),
                    body:    document.getElementById( 'dsk-module-info-body' ),
                };
            }
    
            var wrapper = document.createElement( 'div' );
            wrapper.innerHTML =
                '<div id="dsk-module-info-modal-overlay" class="dsk-modal-overlay">' +
                    '<div class="dsk-modal" role="dialog" aria-modal="true" aria-labelledby="dsk-module-info-title">' +
                        '<div class="dsk-modal-head">' +
                            '<h3 id="dsk-module-info-title" class="dsk-modal-title"></h3>' +
                            '<button type="button" id="dsk-module-info-close" class="dsk-modal-close" aria-label="Close">&times;</button>' +
                        '</div>' +
                        '<div id="dsk-module-info-body" class="dsk-modal-body"></div>' +
                    '</div>' +
                '</div>';
    
            overlay = wrapper.firstElementChild;
            document.body.appendChild( overlay );
    
            var close = document.getElementById( 'dsk-module-info-close' );
    
            close.addEventListener( 'click', function () { overlay.remove(); } );
            overlay.addEventListener( 'click', function ( e ) {
                if ( e.target === overlay ) overlay.remove();
            } );
            document.addEventListener( 'keydown', function onEsc( e ) {
                if ( e.key !== 'Escape' ) return;
                var current = document.getElementById( 'dsk-module-info-modal-overlay' );
                if ( current ) current.remove();
                document.removeEventListener( 'keydown', onEsc );
            } );
    
            return {
                overlay: overlay,
                title:   document.getElementById( 'dsk-module-info-title' ),
                body:    document.getElementById( 'dsk-module-info-body' ),
            };
        }
    
        document.addEventListener( 'click', function ( e ) {
            var infoBtn = e.target.closest( '.dsk-module-info-btn' );
            if ( ! infoBtn ) return;
            var parts = getOrCreateInfoModal();
            parts.title.textContent = infoBtn.getAttribute( 'data-module-name' ) || '';
            parts.body.innerHTML    = infoBtn.getAttribute( 'data-module-infos' ) || '';
        } );
    
        wrapper.classList.add( 'is-ready' );
    };


	// ── Settings tab ──────────────────────────────────────────────────────────

	DSK.initSettings = function () {
		DSK.initSubtabs( {
			chips:  '.dsk-settings-subtabs .dsk-subtab-chip',
			panels: '.dsk-subtab-panel',
		} );

		// Sync dark mode checkbox to current state (class set by app-shell.php)
		var cb = document.querySelector( '[data-setting="switch-light-dark-mode"]' );
		if ( cb ) cb.checked = dskRoot.classList.contains( 'is-dsk-dark' );

		var btn = document.getElementById( 'dsk-settings-save' );
		if ( ! btn ) return;

		// Snapshot initial values to detect changes
		var snapshot = {};
		document.querySelectorAll( '[data-setting]' ).forEach( function ( el ) {
			if ( el.matches( '[data-setting="switch-light-dark-mode"]' ) ) return;
			snapshot[ el.getAttribute( 'data-setting' ) ] = el.type === 'checkbox' ? el.checked : el.value;
		} );

		function syncSaveBtn() {
			var changed = false;
			document.querySelectorAll( '[data-setting]' ).forEach( function ( el ) {
				if ( el.matches( '[data-setting="switch-light-dark-mode"]' ) ) return;
				var key = el.getAttribute( 'data-setting' );
				var val = el.type === 'checkbox' ? el.checked : el.value;
				if ( val !== snapshot[ key ] ) changed = true;
			} );
			btn.disabled = ! changed;
		}

		document.addEventListener( 'change', function ( e ) {
			if ( ! e.target.closest( '.dsk-subtab-panel' ) ) return;
			if ( e.target.matches( '[data-setting="switch-light-dark-mode"]' ) ) return;

			var el  = e.target;
			var key = el.getAttribute( 'data-setting' );
			var row = el.closest( '.dsk-setting-row' );
			if ( ! row || ! key ) return;

			var current = el.type === 'checkbox' ? el.checked : el.value;
			var changed = current !== snapshot[ key ];
			if ( row ) row.classList.toggle( 'is-pending', changed );

			syncSaveBtn();
		} );

		btn.addEventListener( 'click', function () {
			var settings = {};

			document.querySelectorAll( '[data-setting]' ).forEach( function ( el ) {
				if ( el.matches( '[data-setting="switch-light-dark-mode"]' ) ) return;
				settings[ el.getAttribute( 'data-setting' ) ] = el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
			} );

			DSK.setBusy( true, btn );
			DSK.ajax( 'dsk_save_settings', { settings: JSON.stringify( settings ) } )
				.then( function () {
					window.location.reload();
				} )
				.catch( function ( err ) { DSK.setBusy( false, btn ); alert( err.message ); } );
		} );
		
		wrapper.classList.add('is-ready');
	};

} ( window.DSK = window.DSK || {} ) );