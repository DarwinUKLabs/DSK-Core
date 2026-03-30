<?php defined('ABSPATH') || exit;
$tabs = DSK_Admin_Menu::get_tabs();
?>
<script>
(function(){
	if ( localStorage.getItem('dsk_theme') === 'dark' )
		document.documentElement.classList.add('is-dsk-dark');
	if ( localStorage.getItem('dsk_aside_collapsed') === '1' )
		document.documentElement.classList.add('is-dsk-aside-collapsed');
})();
</script>
<div class="dsk-admin">
    <div id="dsk-notices" class="dsk-notices"></div>
    <aside class="dsk-admin-aside">
        <div class="dsk-admin-brand">
            <div class="dsk-icon dsk-admin-icon">
                <?php echo DSK_Helper::get_plugin_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="dsk-admin-title"><?php echo esc_html( DSK_Helper::get_plugin_label() ); ?></div>
            <button type="button" class="dsk-aside-collapse-btn" id="dsk-aside-toggle" aria-label="Toggle sidebar">
                <svg viewBox="0 0 20 20" aria-hidden="true">
                    <path fill="currentColor" d="M5.25 7.5 10 12.25 14.75 7.5 16 8.75 10 14.75 4 8.75z"/>
                </svg>
            </button>
        </div>
        <nav class="dsk-admin-nav dsk-admin-nav-top">
            <?php foreach ($tabs['top'] as $t): ?>
                <a class="dsk-tab"
                   href="<?php echo esc_url($t['href']); ?>"
                   data-tab="<?php echo esc_attr($t['key']); ?>"
                   aria-label="<?php echo esc_attr($t['label']); ?>">
                    <span class="dashicons <?php echo esc_attr($t['icon']); ?>"></span>
                    <span class="dsk-tab-label"><?php echo esc_html($t['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <nav class="dsk-admin-nav dsk-admin-nav-bottom">
            <?php foreach ($tabs['bottom'] as $t): ?>
                <a class="dsk-tab"
                   href="<?php echo esc_url($t['href']); ?>"
                   data-tab="<?php echo esc_attr($t['key']); ?>"
                   aria-label="<?php echo esc_attr($t['label']); ?>">
                    <span class="dashicons <?php echo esc_attr($t['icon']); ?>"></span>
                    <span class="dsk-tab-label"><?php echo esc_html($t['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="dsk-admin-main">
        <div class="dsk-tab-content is-loading">
            <div class="dsk-loading"><span></span><span></span><span></span></div>
        </div>
    </main>
</div>