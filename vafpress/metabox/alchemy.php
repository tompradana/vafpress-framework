<?php

/**
 * Extended version of WPAlchemy Class
 * so that it can process metabox using an array specification
 * and compatible with all Vafpress Framework Option Controls.
 */
class VP_MetaBox_Alchemy extends WPAlchemy_MetaBox
{

	private $things = array();

	/**
	 * Used to setup the meta box content template
	 *
	 * @since	1.0
	 * @access	private
	 * @see		_init()
	 */
	function _setup()
	{
		$this->in_template = TRUE;
		
		// also make current post data available
		global $post;

		// shortcuts
		$mb      =& $this;
		$metabox =& $this;
		$id      =  $this->id;
		$meta    =  $this->_meta(NULL, TRUE);

		// use include because users may want to use one templete for multiple meta boxes
		if( !is_array($this->template) and file_exists($this->template) )
		{
			include $this->template;
		}
		else
		{
			$fields = $this->_enfactor($this->template);
			$this->_enbind($fields);
			$fields = $this->_endep($fields);

			echo '<div class="vp-metabox">';
			echo '<table>';
			echo '<tbody>';
			$this->_enview($fields);
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		}
	 
		// create a nonce for verification
		echo '<input type="hidden" name="'. $this->id .'_nonce" value="' . wp_create_nonce($this->id) . '" />';

		$this->in_template = FALSE;
	}

	function _enfactor($arr)
	{
		$mb            =& $this;
		$fields        = $arr['fields'];
		$field_objects = array();

		foreach ($fields as $field)
		{
			if($field['type'] == 'group' and $field['repeating'])
			{
				$field_objects[$field['name']] = $this->_enfactor_group($field, $mb, true);
			}
			else if($field['type'] == 'group' and !$field['repeating'])
			{
				$field_objects[$field['name']] = $this->_enfactor_group($field, $mb, false);
			}
			else
			{
				$field_objects[$field['name']] = $this->_enfactor_field($field, $mb);
			}
		}

		return $field_objects;
	}

	function _enbind($fields)
	{
		// print_r($fields);
		// print_r($metas);
		foreach ($fields as $name => $field)
		{
			if(is_array($field))
			{
				foreach ($field['groups'] as $group)
				{
					foreach ($group as $f)
					{
						if($f instanceof VP_Control_FieldMulti)
						{
							$bind = $f->get_bind();
							if(!empty($bind))
							{
								$bind   = explode('|', $bind);
								$func   = $bind[0];
								$params = $bind[1];
								$params = explode(',', $params);
								$values = array();
								foreach ($params as $param)
								{
									if(array_key_exists($param, $group))
									{
										$values[] = $group[$param]->get_value();
									}
								}
								$items  = call_user_func_array($func, $values);
								$f->set_items_from_array($items);
							}
						}
					}
				}
			}
			else
			{
				if($field instanceof VP_Control_FieldMulti)
				{
					$bind = $field->get_bind();
					if(!empty($bind))
					{

						$bind   = explode('|', $bind);
						$func   = $bind[0];
						$params = $bind[1];
						$params = explode(',', $params);
						$values = array();
						foreach ($params as $param)
						{
							if(array_key_exists($param, $fields))
							{
								$values[] = $fields[$param]->get_value();
							}
						}
						$items  = call_user_func_array($func, $values);
						$field->set_items_from_array($items);
					}
				}
			}
		}
	}

	function _endep($fields)
	{

		if(!function_exists('loop_fields'))
		{
			function loop_fields(&$fields)
			{
				foreach ($fields as &$field)
				{
					if(is_array($field))
					{
						foreach ($field['groups'] as $group)
						{
							loop_fields($group);
						}
					}

					$dependancy = '';
					if($field instanceof VP_Control_Field)
					{
						$dependancy = $field->get_dependancy();
						if(!empty($dependancy))
						{
							$dependancy = explode('|', $dependancy);
							$func       = $dependancy[0];
							$params     = $dependancy[1];
						}
					}
					else
					{
						if(isset($field['dependancy']))
						{
							if(!empty($field['dependancy']))
							{
								$dependancy = $field['dependancy'];
								$func       = $dependancy['value'];
								$params     = $dependancy['field'];
							}
						}
					}

					if(!empty($dependancy))
					{
						// print_r($dependancy);
						
						$params     = explode(',', $params);
						$values     = array();
						foreach ($params as $param)
						{
							if(array_key_exists($param, $fields))
							{
								// print_r($fields[$param]);
								$values[] = $fields[$param]->get_value();
							}
						}
						// print_r($values);
						$result  = call_user_func_array($func, $values);
						// echo 'result'; var_dump($result);
						if(!$result)
						{
							if($field instanceof VP_Control_Field)
							{
								$field->add_container_extra_classes('hidden');
							}
							else
							{
								$field['container_extra_classes'][] = 'hidden';
							}
							// print_r($field);
						}
					}
				}
			}
		}

		loop_fields($fields);

		return $fields;
	}

	function _enfactor_field($field, $mb, $in_group = false)
	{
		$multiple = array('checkbox', 'checkimage', 'multiselect');

		if( !in_array($field['type'], $multiple) )
		{
			$mb->the_field($field['name']);
		}
		else
		{
			$mb->the_field($field['name'], WPALCHEMY_FIELD_HINT_CHECKBOX_MULTI);						
		}
		$field['name'] = $mb->get_the_name();

		// create the object
		$make     = 'VP_Control_Field_' . $field['type'];
		$vp_field = call_user_func("$make::withArray", $field);

		// get value from mb
		$value    = $mb->get_the_value();
		// get default from array
		$default  = $vp_field->get_default();

		// if value is null and default exist, use default
		if( is_null($value) and !empty($default) )
		{
			$value = $default;				
		}
		// if not the set up value from mb
		else
		{
			if( in_array($field['type'], $multiple) )
			{
				if( !is_array($value) )
					$value = array( $value );
			}
		}
		$vp_field->set_value($value);

		if (!$in_group)
		{
			$vp_field->add_container_extra_classes(array('vp-meta-row', 'vp-meta-single'));
		}

		return $vp_field;
	}

	function _enfactor_group($field, $mb, $repeating)
	{
		$ignore = array('type', 'length', 'fields');
		$groups = array();
		if($repeating)
		{
			while($mb->have_fields_and_multi($field['name']))
			{
				$fields = array();
				foreach ($field['fields'] as $f)
				{
	 				$fields[$f['name']] = $this->_enfactor_field($f, $mb, true);
				}
				$groups[] = $fields;
			}
		}
		else
		{
			while($mb->have_fields($field['name'], $field['length']))
			{
				$fields = array();
				foreach ($field['fields'] as $f)
				{
	 				$fields[$f['name']] = $this->_enfactor_field($f, $mb, true);
				}
				$groups[] = $fields;
			}
		}
		// assign groups
		$group['groups'] = $groups;

		// assign other information
		$keys = array_keys($field);
		foreach ($keys as $key)
		{
			if(!in_array($key, $ignore))
			{
				$group[$key] = $field[$key];
			}
		}
		return $group;
	}

	function _enview($fields)
	{
		foreach ($fields as $name => $field)
		{
			if( is_array($field) and $field['repeating'] )
			{
				echo $this->_render_repeating_group($field);
			}
			else if( is_array($field) and !$field['repeating'] )
			{
				echo $this->_render_group($field);
			}
			else
			{
				echo $this->_render_field($field);
			}
		}
	}

	function _render_field($field)
	{
		return $field->render();
	}

	function _render_group($group)
	{
		$name       = $group['name'];
		// print_r($group);
		$dependancy = isset($group['dependancy']) ? $group['dependancy']['value'] . '|' . $group['dependancy']['field'] : '';

		$html  = '';
		$html .= '<tr id="wpa_loop-' . $name . '" class="vp-meta-group'
		         . (isset($group['container_extra_classes']) ? (' ' . implode(',', $group['container_extra_classes'])) : '')
		         . '"'
		         . VP_Util_Text::return_if_exists(isset($dependancy) ? $dependancy : '', ' data-vp-dependancy="%s"')
                 . '>';
		$html .= '<td colspan="2">';
			$html .= '<table>';
			$html .= '<tbody>';

			foreach ($group['groups'] as $g)
			{
				$html .= '<tr class="vp-wpa-group wpa_group wpa_group-' . $name . '">';
				$html .= '<td>';
					$html .= '<table>';
					$html .= '</tbody>';
					foreach ($g as $f)
					{
						$html .= $this->_render_field($f);
					}
					$html .= '</tbody>';
					$html .= '</table>';
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	function _render_repeating_group($group)
	{
		$name  = $group['name'];

		$dependancy = isset($group['dependancy']) ? $group['dependancy']['value'] . '|' . $group['dependancy']['field'] : '';

		$html  = '';
		$html .= '<tr id="wpa_loop-' . $name
		         . '" class="vp-wpa-loop vp-meta-row wpa_loop wpa_loop-' . $name . ' vp-meta-group'
		         . (isset($group['container_extra_classes']) ? (' ' . implode(',', $group['container_extra_classes'])) : '')
		         . '"'
		         . VP_Util_Text::return_if_exists(isset($dependancy) ? $dependancy : '', 'data-vp-dependancy="%s"')
		         . '>';
		$html .= '<td colspan="2">';
			$html .= '<table>';
			$html .= '<tbody>';

			foreach ($group['groups'] as $g)
			{
				$class = '';
				if ($g === end($group['groups']))   $class = ' last tocopy';
				if ($g === reset($group['groups'])) $class = ' first';
				$html .= '<tr class="vp-wpa-group wpa_group wpa_group-' . $name . $class . '">';
				$html .= '<td>';
					$html .= '<table>';
					$html .= '</tbody>';
					foreach ($g as $f)
					{
						$html .= $this->_render_field($f);
					}
					$html .= '</tbody>';
					$html .= '</table>';
				$html .= '</td>';
				$html .= '<td class="vp-wpa-group-remove">';
				$html .= '<a href="#" class="dodelete" title="Remove">X</a>';
				$html .= '</td>';
				$html .= '</tr>';
			}

				$html .= '<tr>';
				$html .= '<td class="vp-wpa-group-add">';
				$html .= '<a href="#" class="button button-large docopy-' . $name . '">Add More</a>';
				$html .= '</td>';
				$html .= '<td></td>';
				$html .= '</tr>';
			$html .= '</tbody>';
			$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	// get all groups index as array
	function _get_groups_idx()
	{
		$groups = array();
		foreach ($this->template['fields'] as $field)
		{
			if( $field['type'] == 'group' )
			{
				$groups[] = $field['name'];
			}
		}
		return $groups;
	}

	function _get_repeating_groups_idx()
	{
		$groups = array();
		foreach ($this->template['fields'] as $field)
		{
			if( $field['type'] == 'group' and $field['repeating'] )
			{
				$groups[] = $field['name'];
			}
		}
		return $groups;
	}

	function _save($post_id) 
	{
		// skip saving if dev mode is on
		$dev_mode = VP_Util_Config::get_instance()->load('metabox/main', 'dev_mode');
		if($dev_mode)
			return;

		$real_post_id = isset($_POST['post_ID']) ? $_POST['post_ID'] : NULL ;
		
		// check autosave
		if (defined('DOING_AUTOSAVE') AND DOING_AUTOSAVE AND !$this->autosave) return $post_id;
	 
		// make sure data came from our meta box, verify nonce
		$nonce = isset($_POST[$this->id.'_nonce']) ? $_POST[$this->id.'_nonce'] : NULL ;
		if (!wp_verify_nonce($nonce, $this->id)) return $post_id;
	 
		// check user permissions
		if ($_POST['post_type'] == 'page') 
		{
			if (!current_user_can('edit_page', $post_id)) return $post_id;
		}
		else 
		{
			if (!current_user_can('edit_post', $post_id)) return $post_id;
		}
	 
		// authentication passed, save data
		$new_data = isset( $_POST[$this->id] ) ? $_POST[$this->id] : NULL ;

		// remove 'to-copy' item from being saved
		$groups   = $this->_get_groups_idx();
		$r_groups = $this->_get_repeating_groups_idx();
		foreach ($new_data as $key => &$value)
		{
			if( in_array($key, $r_groups) and is_array($value) )
			{
				end($value);
				$key = key($value);
				unset($value[$key]);
			}
		}

		// try to normalize data, since alchemy clean any empty value, it will
		// throw away empty checkbox value, making unchecked checkbox not being saved
		$scheme = $this->_get_scheme();
		// echo 'scheme before';
		// print_r($scheme);
		foreach ($scheme as $key => &$value)
		{
			if( in_array($key, $groups) and is_array($value) )
			{
				$count = count($new_data[$key]);
				$data  = $value[0];
				$value = array();
				for ($i=0; $i < $count; $i++)
				{ 
					array_push($value, $data);
				}
			}
		}
		// echo 'scheme after';
		// print_r($scheme);
	 
	 	// echo 'data before cleaning';
		// print_r($new_data);
		// WPAlchemy_MetaBox::clean($new_data);

	 	// echo 'data after cleaning';
		// print_r($new_data);

		if (empty($new_data))
		{
			$new_data = NULL;
		}

		// continuation of normalizing data
		$new_data = VP_Util_Array::array_merge_recursive_all($scheme, $new_data);

		// echo 'mergerd';
		// print_r($new_data);

		// filter: save
		if ($this->has_filter('save'))
		{
			$new_data = $this->apply_filters('save', $new_data, $real_post_id);

			/**
			 * halt saving
			 * @since 1.3.4
			 */
			if (FALSE === $new_data) return $post_id;

			// WPAlchemy_MetaBox::clean($new_data);
		}

		// get current fields, use $real_post_id (checked for in both modes)
		$current_fields = get_post_meta($real_post_id, $this->id . '_fields', TRUE);

		if ($this->mode == WPALCHEMY_MODE_EXTRACT)
		{
			$new_fields = array();

			if (is_array($new_data))
			{
				foreach ($new_data as $k => $v)
				{
					$field = $this->prefix . $k;
					
					array_push($new_fields,$field);

					$new_value = $new_data[$k];

					if (is_null($new_value))
					{
						delete_post_meta($post_id, $field);
					}
					else
					{
						update_post_meta($post_id, $field, $new_value);
					}
				}
			}

			$diff_fields = array_diff((array)$current_fields,$new_fields);

			if (is_array($diff_fields))
			{
				foreach ($diff_fields as $field)
				{
					delete_post_meta($post_id,$field);
				}
			}

			delete_post_meta($post_id, $this->id . '_fields');

			if ( ! empty($new_fields))
			{
				add_post_meta($post_id,$this->id . '_fields', $new_fields, TRUE);
			}

			// keep data tidy, delete values if previously using WPALCHEMY_MODE_ARRAY
			delete_post_meta($post_id, $this->id);
		}
		else
		{
			if (is_null($new_data))
			{
				delete_post_meta($post_id, $this->id);
			}
			else
			{
				update_post_meta($post_id, $this->id, $new_data);
			}

			// keep data tidy, delete values if previously using WPALCHEMY_MODE_EXTRACT
			if (is_array($current_fields))
			{
				foreach ($current_fields as $field)
				{
					delete_post_meta($post_id, $field);
				}

				delete_post_meta($post_id, $this->id . '_fields');
			}
		}

		// action: save
		if ($this->has_action('save'))
		{
			$this->do_action('save', $new_data, $real_post_id);
		}

		return $post_id;
	}

	private function _get_scheme()
	{

		$this->in_template = TRUE;

		$scheme      = array();
		$curr_group  = '';
		$is_in_group = false;
		$multiple = array('checkbox', 'checkimage', 'multiselect');

		$fields = $this->template;
		$fields = $fields['fields'];

		foreach ($fields as $field)
		{
			if( $field['type'] == 'group' and $field['repeating'] )
			{
				$curr_group          = $field['name'];
				$scheme[$curr_group] = array();
				$is_in_group         = true;
				while($this->have_fields_and_multi($curr_group))
				{
					$ops = array();
					foreach ($field['fields'] as $f)
					{
						if(in_array($f['type'], $multiple))
							$ops[$f['name']] = array();
						else
							$ops[$f['name']] = '';
					}
					$scheme[$curr_group][] = $ops;
				}
				end($scheme[$curr_group]);
				$key = key($scheme[$curr_group]);
				unset($scheme[$curr_group][$key]);
			}
			else if( $field['type'] == 'group' and ! $field['repeating'] )
			{
				$curr_group          = $field['name'];
				$scheme[$curr_group] = array();
				$is_in_group         = true;
				while($this->have_fields($curr_group, $field['length']))
				{
					$ops = array();
					foreach ($field['fields'] as $f)
					{
						if(in_array($f['type'], $multiple))
							$ops[$f['name']] = array();
						else
							$ops[$f['name']] = '';
					}
					$scheme[$curr_group][] = $ops;
				}
			}
			else
			{
				if(in_array($field['type'], $multiple))
					$scheme[$field['name']] = array();
				else
					$scheme[$field['name']] = '';
			}
		}
		return $scheme;
	}

}