<?php
/**
 * Settings > Spam: which checks run, their limits, the site's word lists,
 * the text questions and their bank, and when each form asks.
 *
 * Every control has a label, every group is a fieldset with a legend, and
 * each provider's state is its check box's description. Expects:
 *
 * - $qaf_spam_values  array, from QAF_Spam_Settings::all().
 * - $qaf_spam_states  array, from QAF_Spam_Registry::states().
 * - $qaf_spam_forms   array of id => title.
 * - $qaf_spam_week    array, from QAF_Text_Challenge::stats().
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qaf_n      = QAF_Spam_Settings::OPTION;
$qaf_v      = $qaf_spam_values;
$qaf_on     = function ( $yes ) {
	return $yes ? ' checked' : '';
};
$qaf_sel    = function ( $yes ) {
	return $yes ? ' selected' : '';
};
$qaf_states = array();
foreach ( $qaf_spam_states as $qaf_state ) {
	$qaf_states[ $qaf_state['id'] ] = $qaf_state;
}

// Which checks, in the groups a person thinks in.
$qaf_groups = array(
	array(
		'legend' => __( 'Quiet checks on this site', 'quill-accessible-forms' ),
		'intro'  => __( 'These look at what was sent, never at how someone typed, and send nothing anywhere.', 'quill-accessible-forms' ),
		'ids'    => array( 'honeypot', 'time', 'links', 'patterns', 'blocklist', 'token', 'rate_limit', 'captcha', 'altcha' ),
	),
	array(
		'legend' => __( 'Spam services and security plugins', 'quill-accessible-forms' ),
		'intro'  => __( 'Off until you turn them on. The ones marked as sending answers are listed in the privacy policy text QAF suggests (Settings > Privacy).', 'quill-accessible-forms' ),
		'ids'    => array( 'akismet', 'oopspam', 'cleantalk', 'shield' ),
	),
);
$qaf_descriptions = array(
	'honeypot'   => __( 'Hidden fields people never see or reach. Filled in means a script filled every box.', 'quill-accessible-forms' ),
	'time'       => __( 'Holds a form sent within seconds of the page opening. Only ever a doubt, because people who paste or dictate are quick too.', 'quill-accessible-forms' ),
	'links'      => __( 'Holds a form with more links than the limit below.', 'quill-accessible-forms' ),
	'patterns'   => __( 'Holds answers with script tags, event handlers or forum link codes.', 'quill-accessible-forms' ),
	'blocklist'  => __( 'Uses the Disallowed Comment Keys and Comment Moderation lists from Settings > Discussion, so one list protects comments and forms.', 'quill-accessible-forms' ),
	'token'      => __( 'Notices a page left open a long time or served from a cache. A doubt, never a refusal.', 'quill-accessible-forms' ),
	'rate_limit' => __( 'Holds many sends from one address. Past 100 in a day the person is asked to try again tomorrow.', 'quill-accessible-forms' ),
	'captcha'    => __( 'The CAPTCHA a form already has. reCAPTCHA v3 scores and Turnstile failures only hold, never reject.', 'quill-accessible-forms' ),
	'altcha'     => __( 'A small calculation the browser does in the background: no puzzle, no tracking, nothing sent away. Needs scripts in the browser; without them it is only a doubt.', 'quill-accessible-forms' ),
	'akismet'    => __( 'Sends answers, address, browser and page to Akismet, and tells it about Spam and Not Spam.', 'quill-accessible-forms' ),
	'oopspam'    => __( 'Sends answers, address and email to OOPSpam, which scores them from 0 to 6, and tells it about Spam and Not Spam.', 'quill-accessible-forms' ),
	'cleantalk'  => __( 'Detected only, for now: QAF does not ask it anything until the way it lets other forms ask is confirmed.', 'quill-accessible-forms' ),
	'shield'     => __( 'Detected only, for now: QAF does not ask it anything until the way it lets other forms ask is confirmed.', 'quill-accessible-forms' ),
);
?>
<h2><?php esc_html_e( 'Spam', 'quill-accessible-forms' ); ?></h2>
<p><?php esc_html_e( 'Nothing a check doubts is thrown away. It is kept as spam with the reason, the visitor sees the same thank-you either way, and you can mark it Not Spam to bring it back.', 'quill-accessible-forms' ); ?></p>
<input type="hidden" name="<?php echo esc_attr( $qaf_n ); ?>[_from_page]" value="1" />

<?php foreach ( $qaf_groups as $qaf_g => $qaf_group ) : ?>
<fieldset class="qaf-spam-group">
	<legend><?php echo esc_html( $qaf_group['legend'] ); ?></legend>
	<p id="qaf-spam-group-<?php echo (int) $qaf_g; ?>"><?php echo esc_html( $qaf_group['intro'] ); ?></p>
	<?php
	foreach ( $qaf_group['ids'] as $qaf_id ) :
		$qaf_state = isset( $qaf_states[ $qaf_id ] ) ? $qaf_states[ $qaf_id ] : array( 'label' => $qaf_id, 'note' => '', 'sends_content' => false );
		$qaf_field = 'qaf-spam-provider-' . $qaf_id;
		$qaf_desc  = isset( $qaf_descriptions[ $qaf_id ] ) ? $qaf_descriptions[ $qaf_id ] : '';
		if ( '' !== $qaf_state['note'] ) {
			/* translators: %s: the provider's state, such as "Akismet is active with a key." */
			$qaf_desc .= ' ' . sprintf( __( 'Now: %s', 'quill-accessible-forms' ), $qaf_state['note'] );
		}
		?>
	<p>
		<input type="checkbox" id="<?php echo esc_attr( $qaf_field ); ?>" name="<?php echo esc_attr( $qaf_n ); ?>[providers][<?php echo esc_attr( $qaf_id ); ?>]" value="1" aria-describedby="<?php echo esc_attr( $qaf_field ); ?>-desc"<?php echo $qaf_on( ! empty( $qaf_v['providers'][ $qaf_id ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed attribute. ?> />
		<label for="<?php echo esc_attr( $qaf_field ); ?>"><?php echo esc_html( $qaf_state['label'] ); ?></label>
		<br /><span class="description" id="<?php echo esc_attr( $qaf_field ); ?>-desc"><?php echo esc_html( trim( $qaf_desc ) ); ?></span>
	</p>
	<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>

<fieldset class="qaf-spam-group">
	<legend><?php esc_html_e( 'Limits', 'quill-accessible-forms' ); ?></legend>
	<p>
		<label for="qaf-spam-min-seconds"><?php esc_html_e( 'Shortest time to fill a form, in seconds', 'quill-accessible-forms' ); ?></label><br />
		<input type="number" id="qaf-spam-min-seconds" name="<?php echo esc_attr( $qaf_n ); ?>[min_seconds]" value="<?php echo esc_attr( $qaf_v['min_seconds'] ); ?>" min="<?php echo (int) QAF_Spam_Settings::MIN_SECONDS_FLOOR; ?>" max="120" step="1" class="small-text" aria-describedby="qaf-spam-min-seconds-desc" />
		<br /><span class="description" id="qaf-spam-min-seconds-desc"><?php esc_html_e( 'Never less than 3. Keep it generous: it only ever raises a doubt.', 'quill-accessible-forms' ); ?></span>
	</p>
	<p>
		<label for="qaf-spam-max-links"><?php esc_html_e( 'Most links allowed in one form', 'quill-accessible-forms' ); ?></label><br />
		<input type="number" id="qaf-spam-max-links" name="<?php echo esc_attr( $qaf_n ); ?>[max_links]" value="<?php echo esc_attr( $qaf_v['max_links'] ); ?>" min="0" max="50" step="1" class="small-text" />
	</p>
	<p>
		<label for="qaf-spam-oopspam-threshold"><?php esc_html_e( 'OOPSpam score that holds a form (1 to 6)', 'quill-accessible-forms' ); ?></label><br />
		<input type="number" id="qaf-spam-oopspam-threshold" name="<?php echo esc_attr( $qaf_n ); ?>[oopspam_threshold]" value="<?php echo esc_attr( $qaf_v['oopspam_threshold'] ); ?>" min="1" max="6" step="1" class="small-text" aria-describedby="qaf-spam-oopspam-threshold-desc" />
		<br /><span class="description" id="qaf-spam-oopspam-threshold-desc"><?php esc_html_e( 'OOPSpam scores each form from 0 (clean) to 6 (spam). 3 is a sensible start.', 'quill-accessible-forms' ); ?></span>
	</p>
	<p>
		<label for="qaf-spam-oopspam-key"><?php esc_html_e( 'OOPSpam API key', 'quill-accessible-forms' ); ?></label><br />
		<input type="password" id="qaf-spam-oopspam-key" name="<?php echo esc_attr( $qaf_n ); ?>[oopspam_api_key]" value="" autocomplete="off" class="regular-text" aria-describedby="qaf-spam-oopspam-key-desc" />
		<br /><span class="description" id="qaf-spam-oopspam-key-desc"><?php echo esc_html( '' !== $qaf_v['oopspam_api_key'] ? __( 'A key is saved. Leave this empty to keep it, or type a new one to replace it.', 'quill-accessible-forms' ) : __( 'No key saved yet.', 'quill-accessible-forms' ) ); ?></span>
	</p>
	<?php if ( '' !== $qaf_v['oopspam_api_key'] ) : ?>
	<p>
		<input type="checkbox" id="qaf-spam-oopspam-key-clear" name="<?php echo esc_attr( $qaf_n ); ?>[oopspam_api_key_clear]" value="1" />
		<label for="qaf-spam-oopspam-key-clear"><?php esc_html_e( 'Remove the saved OOPSpam key', 'quill-accessible-forms' ); ?></label>
	</p>
	<?php endif; ?>
	<p>
		<label for="qaf-spam-altcha-max"><?php esc_html_e( 'Proof of work: how much work, as the largest number searched', 'quill-accessible-forms' ); ?></label><br />
		<input type="number" id="qaf-spam-altcha-max" name="<?php echo esc_attr( $qaf_n ); ?>[altcha_max_number]" value="<?php echo esc_attr( $qaf_v['altcha_max_number'] ); ?>" min="1000" max="1000000" step="1000" class="regular-text" aria-describedby="qaf-spam-altcha-max-desc" />
		<br /><span class="description" id="qaf-spam-altcha-max-desc"><?php esc_html_e( '50000 takes a second or two on a phone. Each wrong answer to a question doubles it for that address, up to sixteen times.', 'quill-accessible-forms' ); ?></span>
	</p>
</fieldset>

<fieldset class="qaf-spam-group">
	<legend><?php esc_html_e( 'The site\'s word lists', 'quill-accessible-forms' ); ?></legend>
	<p id="qaf-spam-lists-desc">
		<?php esc_html_e( 'Edit the lists themselves in Settings > Discussion:', 'quill-accessible-forms' ); ?>
		<a href="<?php echo esc_url( admin_url( 'options-discussion.php' ) ); ?>"><?php esc_html_e( 'Discussion Settings', 'quill-accessible-forms' ); ?></a>
	</p>
	<p>
		<input type="checkbox" id="qaf-spam-use-disallowed" name="<?php echo esc_attr( $qaf_n ); ?>[use_disallowed]" value="1"<?php echo $qaf_on( ! empty( $qaf_v['use_disallowed'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
		<label for="qaf-spam-use-disallowed"><?php esc_html_e( 'Hold forms that contain a Disallowed Comment Key', 'quill-accessible-forms' ); ?></label>
	</p>
	<p>
		<input type="checkbox" id="qaf-spam-use-moderation" name="<?php echo esc_attr( $qaf_n ); ?>[use_moderation]" value="1"<?php echo $qaf_on( ! empty( $qaf_v['use_moderation'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
		<label for="qaf-spam-use-moderation"><?php esc_html_e( 'Treat a Comment Moderation word as a doubt (a question is asked, or the form is held)', 'quill-accessible-forms' ); ?></label>
	</p>
</fieldset>

<fieldset class="qaf-spam-group">
	<legend><?php esc_html_e( 'Text questions', 'quill-accessible-forms' ); ?></legend>
	<p><?php esc_html_e( 'A short question in words, which works the same by ear, by braille, by switch and by voice. It is never the only check, and after three tries the visitor can send the form anyway for a person to read.', 'quill-accessible-forms' ); ?></p>
	<p>
		<input type="checkbox" id="qaf-spam-provider-challenge" name="<?php echo esc_attr( $qaf_n ); ?>[providers][challenge]" value="1"<?php echo $qaf_on( ! empty( $qaf_v['providers']['challenge'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
		<label for="qaf-spam-provider-challenge"><?php esc_html_e( 'Ask text questions', 'quill-accessible-forms' ); ?></label>
	</p>
	<fieldset>
		<legend><?php esc_html_e( 'When to ask', 'quill-accessible-forms' ); ?></legend>
		<?php
		$qaf_mode_labels = array(
			QAF_Spam_Settings::ASK_WHEN_UNSURE => __( 'Only when the other checks are unsure (recommended)', 'quill-accessible-forms' ),
			QAF_Spam_Settings::ASK_ALWAYS      => __( 'Always, on every form (for a form under attack)', 'quill-accessible-forms' ),
			QAF_Spam_Settings::ASK_NEVER       => __( 'Never; hold doubtful forms instead', 'quill-accessible-forms' ),
		);
		foreach ( $qaf_mode_labels as $qaf_mode => $qaf_label ) :
			?>
		<p>
			<input type="radio" id="qaf-spam-mode-<?php echo esc_attr( $qaf_mode ); ?>" name="<?php echo esc_attr( $qaf_n ); ?>[challenge_mode]" value="<?php echo esc_attr( $qaf_mode ); ?>"<?php echo $qaf_on( $qaf_v['challenge_mode'] === $qaf_mode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			<label for="qaf-spam-mode-<?php echo esc_attr( $qaf_mode ); ?>"><?php echo esc_html( $qaf_label ); ?></label>
		</p>
		<?php endforeach; ?>
	</fieldset>
	<fieldset>
		<legend><?php esc_html_e( 'Kinds of question', 'quill-accessible-forms' ); ?></legend>
		<?php
		$qaf_kind_labels = array(
			'sense'       => __( 'Everyday sense: "Which is colder, snow or soup?"', 'quill-accessible-forms' ),
			'instruction' => __( 'Following an instruction: "Type the second word of this sentence."', 'quill-accessible-forms' ),
			'choice'      => __( 'Choosing from a short list, also as radio buttons: "Which of these is an animal: chair, horse, window?"', 'quill-accessible-forms' ),
			'order'       => __( 'Putting things in order: "Put these in order, smallest first: town, city, house."', 'quill-accessible-forms' ),
			'maths'       => __( 'Simple sums: "What is seven plus two?" Off by default, because numbers are a barrier for people with dyscalculia.', 'quill-accessible-forms' ),
		);
		foreach ( $qaf_kind_labels as $qaf_kind => $qaf_label ) :
			?>
		<p>
			<input type="checkbox" id="qaf-spam-kind-<?php echo esc_attr( $qaf_kind ); ?>" name="<?php echo esc_attr( $qaf_n ); ?>[challenge_kinds][<?php echo esc_attr( $qaf_kind ); ?>]" value="1"<?php echo $qaf_on( ! empty( $qaf_v['challenge_kinds'][ $qaf_kind ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			<label for="qaf-spam-kind-<?php echo esc_attr( $qaf_kind ); ?>"><?php echo esc_html( $qaf_label ); ?></label>
		</p>
		<?php endforeach; ?>
	</fieldset>
	<p>
		<label for="qaf-spam-bank"><?php esc_html_e( 'Your own questions', 'quill-accessible-forms' ); ?></label><br />
		<textarea id="qaf-spam-bank" name="<?php echo esc_attr( $qaf_n ); ?>[challenge_bank]" rows="6" cols="60" class="large-text" aria-describedby="qaf-spam-bank-desc"><?php echo esc_textarea( $qaf_v['challenge_bank'] ); ?></textarea>
		<br /><span class="description" id="qaf-spam-bank-desc"><?php esc_html_e( 'One question per line, then a bar and each right answer, also separated by bars. For example: What color is the sky on a clear day? | blue. Ask what anyone using the form would know, not facts about your site.', 'quill-accessible-forms' ); ?></span>
	</p>
	<p>
		<label for="qaf-spam-send-anyway"><?php esc_html_e( 'Offer Send Anyway after this many wrong answers', 'quill-accessible-forms' ); ?></label>
		<input type="number" id="qaf-spam-send-anyway" name="<?php echo esc_attr( $qaf_n ); ?>[send_anyway_after]" value="<?php echo esc_attr( (int) $qaf_v['send_anyway_after'] ); ?>" min="<?php echo esc_attr( QAF_Spam_Settings::SEND_ANYWAY_MIN ); ?>" max="<?php echo esc_attr( QAF_Spam_Settings::SEND_ANYWAY_MAX ); ?>" class="small-text" aria-describedby="qaf-spam-send-anyway-desc" />
		<br /><span class="description" id="qaf-spam-send-anyway-desc"><?php esc_html_e( 'Whoever takes Send Anyway is never turned away: the form is held for you to look at.', 'quill-accessible-forms' ); ?></span>
	</p>
	<p><?php
		/* translators: 1: questions asked, 2: answered, 3: wrong answers, 4: sent anyway. */
		echo esc_html( sprintf( __( 'This week: asked %1$d times, %2$d answered, %3$d wrong answers, %4$d sent anyway.', 'quill-accessible-forms' ), $qaf_spam_week['asked'], $qaf_spam_week['passed'], $qaf_spam_week['failed'], $qaf_spam_week['sent_anyway'] ) );
	?></p>
</fieldset>

<?php if ( $qaf_spam_forms ) : ?>
<fieldset class="qaf-spam-group">
	<legend><?php esc_html_e( 'When each form asks', 'quill-accessible-forms' ); ?></legend>
	<?php
	foreach ( $qaf_spam_forms as $qaf_form_id => $qaf_title ) :
		$qaf_current = isset( $qaf_v['form_modes'][ $qaf_form_id ] ) ? $qaf_v['form_modes'][ $qaf_form_id ] : '';
		$qaf_select  = 'qaf-spam-form-mode-' . (int) $qaf_form_id;
		?>
	<p>
		<label for="<?php echo esc_attr( $qaf_select ); ?>"><?php echo esc_html( $qaf_title ); ?></label><br />
		<select id="<?php echo esc_attr( $qaf_select ); ?>" name="<?php echo esc_attr( $qaf_n ); ?>[form_modes][<?php echo (int) $qaf_form_id; ?>]">
			<option value=""<?php echo $qaf_sel( '' === $qaf_current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'As the site is set', 'quill-accessible-forms' ); ?></option>
			<option value="<?php echo esc_attr( QAF_Spam_Settings::ASK_WHEN_UNSURE ); ?>"<?php echo $qaf_sel( QAF_Spam_Settings::ASK_WHEN_UNSURE === $qaf_current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Ask when unsure', 'quill-accessible-forms' ); ?></option>
			<option value="<?php echo esc_attr( QAF_Spam_Settings::ASK_ALWAYS ); ?>"<?php echo $qaf_sel( QAF_Spam_Settings::ASK_ALWAYS === $qaf_current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Always ask', 'quill-accessible-forms' ); ?></option>
			<option value="<?php echo esc_attr( QAF_Spam_Settings::ASK_NEVER ); ?>"<?php echo $qaf_sel( QAF_Spam_Settings::ASK_NEVER === $qaf_current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Never ask', 'quill-accessible-forms' ); ?></option>
		</select>
	</p>
	<?php endforeach; ?>
</fieldset>
<?php endif; ?>
