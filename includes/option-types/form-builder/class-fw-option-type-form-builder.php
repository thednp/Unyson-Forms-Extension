<?php if (!defined('FW')) die('Forbidden');

/**
 * Default form builder
 * Other form types may define and use new form builders
 */
class FW_Option_Type_Form_Builder extends FW_Option_Type_Builder
{
	public function get_type()
	{
		return 'form-builder';
	}

	/**
	 * @internal
	 */
	protected function _init()
	{
		$dir = dirname(__FILE__);

		require $dir .'/extends/class-fw-option-type-form-builder-item.php';
		require $dir .'/items/form-builder-items.php';
	}

	/**
	 * @param FW_Option_Type_Builder_Item $item_type_instance
	 * @return bool
	 */
	protected function item_type_is_valid($item_type_instance)
	{
		return is_subclass_of($item_type_instance, 'FW_Option_Type_Form_Builder_Item');
	}

	/**
	 * @internal
	 * {@inheritdoc}
	 */
	protected function _enqueue_static($id, $option, $data)
	{
		parent::_enqueue_static($id, $option, $data);

		wp_enqueue_style(
			'fw-builder-'. $this->get_type(),
			fw_get_framework_directory_uri('/extensions/forms/includes/option-types/'. $this->get_type() .'/static/css/styles.css'),
			array('fw')
		);
		wp_enqueue_script(
			'fw-builder-'. $this->get_type(),
			fw_get_framework_directory_uri('/extensions/forms/includes/option-types/'. $this->get_type() .'/static/js/helpers.js'),
			array('fw'),
			false,
			true
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_value_from_items($items)
	{
		static $recursion_level = 0;

		/** prevent duplicate shortcodes */
		static $found_shortcodes = array();

		/**
		 * @var FW_Option_Type_Builder_Item[] $item_types
		 */
		$item_types = $this->get_item_types();

		$fixed_items = array();

		foreach ($items as $item_attributes) {
			if (!isset($item_attributes['type']) || !isset($item_types[ $item_attributes['type'] ])) {
				// invalid item type
				continue;
			}

			$fixed_item_attributes = $item_types[ $item_attributes['type'] ]->get_value_from_attributes($item_attributes);

			// check if required attribute is set and it is unique
			{
				if (
					empty($fixed_item_attributes['shortcode'])
					||
					isset($found_shortcodes[ $fixed_item_attributes['shortcode'] ])
				) {
					$fixed_item_attributes['shortcode'] = sanitize_key(
						str_replace('-', '_', $item_attributes['type']) .'_'. substr(fw_rand_md5(), 0, 7)
					);
				}

				$found_shortcodes[ $fixed_item_attributes['shortcode'] ] = true;
			}

			if (isset($fixed_item_attributes['_items'])) {
				// item leaved _items key, this means that it has/accepts items in it

				$recursion_level++;

				$fixed_item_attributes['_items'] = $this->get_value_from_items($fixed_item_attributes['_items']);

				$recursion_level--;
			}

			$fixed_items[] = $fixed_item_attributes;

			unset($fixed_item_attributes);
		}

		/**
		 * this will be real return (not inside a recursion)
		 * make some clean up
		 */
		if (!$recursion_level) {
			$found_shortcodes = array();
		}

		return $fixed_items;
	}

	/**
	 * Generate html form for frontend from builder items
	 * @param array $items Builder array value json decoded
	 * @param array $input_values {shortcode => value} Usually values from _POST
	 * @return string HTML
	 */
	public function frontend_render(array $items, array $input_values)
	{
		return fw_render_view(
			$this->locate_path('/views/items.php', dirname(__FILE__) .'/views/items.php'),
			array(
				'items_html' => $this->render_items($items, $input_values)
			)
		);
	}

	/**
	 * Loop through each item and ask to validate its POST value
	 * @param array $items
	 * @param array $input_values {shortcode => value} Usually values from _POST
	 * @return array errors
	 */
	public function frontend_validate(array $items, array $input_values)
	{
		/**
		 * @var FW_Option_Type_Builder_Item[] $item_types
		 */
		$item_types = $this->get_item_types();

		$errors = array();

		foreach ($items as $item) {
			if (!isset($item_types[ $item['type'] ])) {
				trigger_error('Invalid form item type: '. $item['type'], E_USER_WARNING);
				continue;
			}

			$input_value = isset($input_values[$item['shortcode']]) ? $input_values[$item['shortcode']] : null;

			$error = $item_types[ $item['type'] ]->frontend_validate($item, $input_value);

			if ($error) {
				$errors[ $item['shortcode'] ] = $error;
				break;
			}

			if (isset($item['_items'])) {
				$sub_errors = $this->frontend_validate($item['_items'], $input_values);

				if (!empty($sub_errors)) {
					$errors = array_merge($errors, $sub_errors);
				}
			}
		}

		return $errors;
	}

	/**
	 * Form items value after submit and successful validation
	 * @param array $items
	 * @param array $input_values {shortcode => value} Usually values from _POST
	 * @return array
	 */
	public function frontend_get_value_from_items(array $items, array $input_values)
	{
		/**
		 * @var FW_Option_Type_Builder_Item[] $item_types
		 */
		$item_types = $this->get_item_types();

		$values = array();

		foreach ($items as $item) {
			if (!isset($item_types[ $item['type'] ])) {
				trigger_error('Invalid form item type: '. $item['type'], E_USER_WARNING);
				continue;
			}

			if (isset($values[ $item['shortcode'] ])) {
				trigger_error('Form item duplicate shortcode: '. $item['shortcode'], E_USER_WARNING);
			}

			$values[ $item['shortcode'] ] = isset($input_values[$item['shortcode']]) ? $input_values[$item['shortcode']] : null;

			if (isset($item['_items'])) {
				$sub_values = $this->frontend_get_value_from_items($item['_items'], $input_values);

				if (!empty($sub_values)) {
					$values = array_merge($values, $sub_values);
				}
			}
		}

		return $values;
	}

	/**
	 * Render items
	 *
	 * This method can be used recursive by items that has another items inside
	 *
	 * @param array $items
	 * @param array $input_values
	 *
	 * @return string
	 */
	public function render_items(array $items, array $input_values)
	{
		/**
		 * @var FW_Option_Type_Form_Builder_Item[] $item_types
		 */
		$item_types = $this->get_item_types();

		$html = '';

		foreach ($items as $item) {
			if (!isset($item_types[ $item['type'] ])) {
				trigger_error('Invalid form item type: '. $item['type'], E_USER_WARNING);
				continue;
			}

			$input_value = isset($input_values[$item['shortcode']]) ? $input_values[$item['shortcode']] : null;

			$html .= $item_types[ $item['type'] ]->frontend_render($item, $input_value);
		}

		return $html;
	}

	/**
	 * Search relative path in '/extensions/forms/{builder_type}/'
	 *
	 * @param string $rel_path
	 * @param string $default_path Used if no path found
	 *
	 * @return false|string
	 */
	private function locate_path($rel_path, $default_path)
	{
		if ($path = fw()->extensions->get('forms')->locate_path('/'. $this->get_type() . $rel_path)) {
			return $path;
		} else {
			return $default_path;
		}
	}
}
FW_Option_Type::register('FW_Option_Type_Form_Builder');
