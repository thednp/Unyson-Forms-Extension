<?php if (!defined('FW')) die('Forbidden');
/**
 * @var int $form_id
 * @var array $form_values
 * @var array $shortcode_to_item
 */
?>

<table align="center" border="0" cellpadding="10">
	<thead>
		<tr>
			<th colspan="2"><h1><?php echo fw_htmlspecialchars(get_the_title($form_id)) ?></h1></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($form_values as $shortcode => $form_value): ?>
		<?php
		$item = &$shortcode_to_item[$shortcode];
		$item_options = &$item['options'];

		switch ($item['type']) {
			case 'checkboxes':
				$title = fw_htmlspecialchars($item_options['label']);
				$value = implode(', ', $form_value);
				break;
			case 'textarea':
				$title = fw_htmlspecialchars($item_options['label']);
				$value = '<pre>'. fw_htmlspecialchars($form_value) .'</pre>';
				break;
			default:
				$title = fw_htmlspecialchars($item_options['label']);

				if (is_array($form_value)) {
					$value = '<pre>'. fw_htmlspecialchars( print_r($form_value, true) ) .'</pre>';
				} else {
					$value = fw_htmlspecialchars( $form_value );
				}
		}
		?>
		<tr>
			<td valign="top"><b><?php echo $title ?></b></td>
			<td valign="top"><?php echo $value ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>