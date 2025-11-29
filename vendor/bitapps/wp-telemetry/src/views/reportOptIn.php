<div class="updated cforge-telemetry-notice" style="border: 1px solid #c3c4c7; padding: 15px; position: relative;">
	<h1 style="padding: 0; margin: 0 0 10px 0; font-size: 18px;"><?php echo esc_html(sprintf(__('We hope you love %s!', 'content-forge'), $args['title'])); ?></h1>

	<p style="margin: 0 0 15px 0; line-height: 1.5;">
		<?php echo $args['description']; ?>
	</p>

	<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
		<a href="<?php echo esc_attr(esc_url($args['optInUrl'])); ?>" class="button-primary button-large" style="margin: 0;"><?php esc_html_e('Allow & Continue', 'content-forge'); ?></a>
		<a href="<?php echo esc_attr(esc_url($args['optOutUrl'])); ?>" class="button-secondary button-large" style="border-color: transparent; margin: 0;"><?php esc_html_e('Skip', 'content-forge'); ?></a>
		<?php if (!empty($args['dataWeCollect']) && is_array($args['dataWeCollect'])) { ?>
		<button type="button" class="button-link cforge-data-collect-btn" style="margin-left: 5px; text-decoration: none; cursor: pointer;">
			<?php esc_html_e('Data We Collect', 'content-forge'); ?>
		</button>
		<?php } ?>
		<?php if (!empty($args['termsUrl']) || !empty($args['policyUrl'])) { ?>
		<span style="margin-left: auto; font-size: 12px;">
			<?php if (!empty($args['termsUrl'])) { ?>
			<a href="<?php echo esc_url($args['termsUrl']); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none;"><?php esc_html_e('Terms', 'content-forge'); ?></a>
			<?php } ?>
			<?php if (!empty($args['termsUrl']) && !empty($args['policyUrl'])) { ?> | <?php } ?>
			<?php if (!empty($args['policyUrl'])) { ?>
			<a href="<?php echo esc_url($args['policyUrl']); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none;"><?php esc_html_e('Privacy', 'content-forge'); ?></a>
			<?php } ?>
		</span>
		<?php } ?>
	</div>
</div>

<?php if (!empty($args['dataWeCollect']) && is_array($args['dataWeCollect'])) { ?>
<!-- Data Collection Modal -->
<div id="cforge-data-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
	<div style="background-color: #fff; margin: 5% auto; padding: 25px; border: 1px solid #c3c4c7; width: 90%; max-width: 600px; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;">
		<span class="cforge-modal-close" style="position: absolute; right: 15px; top: 15px; font-size: 28px; font-weight: bold; color: #50575e; cursor: pointer; line-height: 1;">&times;</span>
		<h2 style="margin: 0 0 15px 0; padding-right: 30px; font-size: 20px;"><?php esc_html_e('Data We Collect', 'content-forge'); ?></h2>
		<p style="margin: 0 0 20px 0; color: #50575e; line-height: 1.6;">
			<?php esc_html_e('We only collect anonymous technical data to improve the plugin—no personal information, emails, or sensitive data. Your privacy is our priority, and you can opt-out anytime.', 'content-forge'); ?>
		</p>
		<ul style="margin: 0; padding-left: 20px; color: #1d2327; line-height: 1.8;">
			<?php foreach ($args['dataWeCollect'] as $item) { ?>
				<li style="margin-bottom: 8px;"><?php echo esc_html($item); ?></li>
			<?php } ?>
		</ul>
		<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dcdcde;">
			<a href="<?php echo esc_attr(esc_url($args['optInUrl'])); ?>" class="button-primary button-large"><?php esc_html_e('Allow & Continue', 'content-forge'); ?></a>
			<button type="button" class="button cforge-modal-close" style="margin-left: 10px;"><?php esc_html_e('Close', 'content-forge'); ?></button>
		</div>
	</div>
</div>

<script>
(function() {
	var modal = document.getElementById('cforge-data-modal');
	var btn = document.querySelector('.cforge-data-collect-btn');
	var closeBtns = document.querySelectorAll('.cforge-modal-close');
	
	if (btn && modal) {
		btn.addEventListener('click', function() {
			modal.style.display = 'block';
		});
	}
	
	closeBtns.forEach(function(closeBtn) {
		closeBtn.addEventListener('click', function() {
			modal.style.display = 'none';
		});
	});
	
	if (modal) {
		window.addEventListener('click', function(event) {
			if (event.target === modal) {
				modal.style.display = 'none';
			}
		});
	}
})();
</script>
<?php } ?>