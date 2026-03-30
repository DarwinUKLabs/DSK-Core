<?php defined( 'ABSPATH' ) || exit;
$active_modules   = DSK_Module_Registry::get_active();
$inactive_modules = DSK_Module_Registry::get_inactive();

/**
 * Groups modules by their first tag, then sorts each group alphabetically.
 * Modules with no tags fall into a 'none' group rendered last.
 *
 * @param  array $modules
 * @return array<string, array>  [ tag_slug => [ 'tag' => array|null, 'modules' => array ] ]
 */
function dsk_group_modules( array $modules ): array {
	$groups = [];

	foreach ( $modules as $module ) {
		$first_tag = $module['tags'][0] ?? null;
		$key       = $first_tag ? $first_tag['slug'] : 'none';

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = [ 'tag' => $first_tag, 'modules' => [] ];
		}
		$groups[ $key ]['modules'][] = $module;
	}

	// Sort modules alphabetically within each group
	foreach ( $groups as &$group ) {
		usort( $group['modules'], fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	}
	unset( $group );

	// Sort groups: known tags in definition order, 'none' last
	$tag_order = array_keys( DSK_Module_Registry::get_tags() );
	uksort( $groups, function( $a, $b ) use ( $tag_order ) {
		$ia = $a === 'none' ? PHP_INT_MAX : ( array_search( $a, $tag_order ) ?: PHP_INT_MAX );
		$ib = $b === 'none' ? PHP_INT_MAX : ( array_search( $b, $tag_order ) ?: PHP_INT_MAX );
		return $ia <=> $ib;
	} );

	return $groups;
}

/**
 * Renders grouped module rows with group titles.
 *
 * @param array $modules
 * @param bool  $checked  Whether the checkbox should be checked.
 */
function dsk_render_module_groups( array $modules, bool $checked ): void {
	foreach ( dsk_group_modules( $modules ) as $group ) :
		$label = $group['tag'] ? $group['tag']['label'] : __( 'Other', 'dsk-core' );
		?>
		<div class="dsk-modules-list dsk-tag-group">
			<p class="dsk-tag-group-title"><?php echo esc_html( $label ); ?></p>
			<?php foreach ( $group['modules'] as $module ) : ?>
			<div class="dsk-module-row<?php echo $checked ? ' is-active' : ''; ?>" data-slug="<?php echo esc_attr( $module['slug'] ); ?>">
				<div class="dsk-icon" style="background-color: <?php echo esc_attr( $module['color'] ); ?>;">
					<span class="dashicons <?php echo esc_attr( $module['icon'] ); ?>"></span>
				</div>
				<div class="dsk-module-content">
					<div class="dsk-module-header">
						<h3 class="dsk-module-name"><?php echo esc_html( $module['name'] ); ?></h3>
						<?php if ( ! empty( $module['tags'] ) ) : ?>
						<div class="dsk-module-tags">
							<?php foreach ( $module['tags'] as $tag ) : ?>
							<span class="dsk-tag" style="--tag-color:<?php echo esc_attr( $tag['color'] ); ?>"><?php echo esc_html( $tag['label'] ); ?></span>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php if ( $module['description'] ) : ?>
					<p class="dsk-module-description"><?php echo esc_html( $module['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $module['infos'] ) ) : ?>
				<button type="button" class="dsk-module-info-btn" aria-label="Module info"
					data-module-name="<?php echo esc_attr( $module['name'] ); ?>"
					data-module-infos="<?php echo esc_attr( $module['infos'] ); ?>">?</button>
				<?php endif; ?>
				<div class="dsk-module-toggle">
					<label class="dsk-switch">
						<input type="checkbox" <?php echo $checked ? 'checked' : ''; ?> data-slug="<?php echo esc_attr( $module['slug'] ); ?>">
						<span class="dsk-slider"></span>
					</label>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach;
}
?>
<div class="dsk-tab-header">
	<div class="dsk-tab-title">
		<h1>Dashboard</h1>
		<p>Manage your Dev Site Kit modules. Toggle to activate or deactivate.</p>
	</div>
	<div class="dsk-tools">
		<button type="button" class="button button-primary dsk-submit" disabled>Save</button>
	</div>
</div>
<?php if ( ! empty( $active_modules ) || ! empty( $inactive_modules ) ) : ?>
<div class="dsk-subtabs dsk-dashboard-subtabs">
	<button type="button" class="dsk-subtab-chip is-active" data-view="active">
		Active
		<span class="dsk-badge" id="dsk-badge-active"><?php echo count( $active_modules ); ?></span>
	</button>
	<button type="button" class="dsk-subtab-chip" data-view="inactive">
		Inactive
		<span class="dsk-badge" id="dsk-badge-inactive"><?php echo count( $inactive_modules ); ?></span>
	</button>
</div>
<?php endif; ?>
<div class="dsk-tab-inner">
	<?php if ( ! empty( $active_modules ) || ! empty( $inactive_modules ) ) : ?>
	<div class="dsk-subtab-panel" id="dsk-modules-active">
		<?php dsk_render_module_groups( $active_modules, true ); ?>
		<p class="dsk-modules-footer">Looking for more modules? Check our <a href="https://darwin-labs.co.uk/" target="_blank" rel="noopener">website</a>.</p>
	</div>
	<div class="dsk-subtab-panel" id="dsk-modules-inactive" style="display:none;">
		<?php dsk_render_module_groups( $inactive_modules, false ); ?>
		<p class="dsk-modules-footer">Looking for more modules? Check our <a href="https://darwin-labs.co.uk/" target="_blank" rel="noopener">website</a>.</p>
	</div>
	<?php else : ?>
	<div class="dsk-empty-state dsk-card" style="height:100%;">
		<span class="dashicons dashicons-warning"></span>
		<p class="dsk-empty-state-title">No modules found. Install DSK module plugins to get started.</p>
		<p class="dsk-empty-state-desc">To use the Darwin Site Kit Core as intended you'll need to install our compatible modules. You can find these modules in the WordPress plugin library or on darwin-labs.co.uk.</p>
		<div class="dsk-empty-state-actions">
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=DSK&tab=search&type=term' ) ); ?>" class="button">Visit WordPress Plugin Page</a>
			<a href="https://darwin-labs.co.uk/" target="_blank" rel="noopener" class="button button-primary">Visit Darwin Labs</a>
		</div>
	</div>
	<?php endif; ?>
</div>