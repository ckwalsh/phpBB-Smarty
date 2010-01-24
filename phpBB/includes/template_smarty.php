<?php

/**
* phpBB Smarty Compatibility Library
* Written by Cullen Walsh
* Released under the GNU General Public License
**/

require($phpbb_root_path . 'vendor/Smarty/libs/Smarty.class.php');

/**
* Smarty compiler function to handle language variables
* If they are defined, it will directly place them into the template
* If they are not explicitly defined, they will insert php to properly handle them
**/

function smarty_compiler_L($tag_arg, &$smarty)
{
	global $user;
	$tag_arg = preg_replace('/[^A-Z_]*/', '', $tag_arg);
	if (isset($user->lang[$tag_arg]))
	{
		return "?>{$user->lang[$tag_arg]}<?php";
	}
	else
	{
		return 'echo(isset($this->_tpl_vars[\'L_' . $tag_arg . '\']) ? $this->_tpl_vars[\'L_' . $tag_arg . '\'] : \'{L ' . $tag_arg . '}\');';
	}
}

function smarty_compiler_LA($tag_arg, &$smarty)
{
	global $user;
	$tag_arg = preg_replace('/[^A-Z_]*/', '', $tag_arg);
	if (isset($user->lang[$tag_arg]))
	{
		return '?>' . addslashes($user->lang[$tag_arg]) . '<?php';
	}
	else
	{
		return 'echo(isset($this->_tpl_vars[\'LA_' . $tag_arg . '\']) ? addslashes($this->_tpl_vars[\'LA_' . $tag_arg . '\']) : \'{LA ' . $tag_arg . '}\');';
	}
}

/**
* Smarty post-filter
* Removes extra php tags that only contain whitespace
* These tags may be formed by the language replacements
**/
function smarty_postfilter_strip($compiled, &$smarty)
{
	return preg_replace('/<\?php\s+\?>/', '', $compiled);
}

class TemplateSmarty
{

	private $smarty;
	
	private $_files = array();
	private $_displayed_files = array();
	
	private $_block_vars = array();
	private $_last_blocks = array();

	function __construct()
	{
		global $phpbb_root_path;
		
		$this->smarty = new Smarty;
		$this->smarty->compile_dir = $phpbb_root_path . 'cache';
		$this->smarty->register_compiler_function('L', 'smarty_compiler_L', true);
		$this->smarty->register_compiler_function('LA', 'smarty_compiler_LA', true);
		$this->smarty->register_postfilter('smarty_postfilter_strip');
	}

	function set_template()
	{
		global $phpbb_root_path, $user;
		
		$this->smarty->template_dir = $phpbb_root_path . 'styles/' . $user->theme['template_path'] . '/template';
		$path = $user->theme['template_path'];
		/*
		if($user->theme['template_inherits_id'])
		{
			$path[] = $user->theme['template_inherits_path'];
		}
		*/
		$this->smarty->compile_id = $path . '_' . $user->data['user_lang'];
		
		return true;
	}
	
	function set_custom_template($template_path, $template_name, $template_mode = 'template')
	{
		global $user;
		
		$this->smarty->template_dir = $template_path;
		$this->smarty->compile_id = $template_name . $user->data['user_lang'];
		return true;
	}
	
	function set_filenames($filename_array)
	{
		$this->_files = $filename_array + $this->_files;
	}
	
	function destroy()
	{
		$this->_files = array();
		$this->smarty = null;
	}
	
	function display($handle, $include_once = true)
	{
		global $user;
		
		if(!$include_once || !isset($this->displayed_files[$handle]))
		{
			$this->_setup_block_vars();
			$this->smarty->assign_by_ref('_user', $user);
			$this->smarty->display($this->_files[$handle]);
			$this->displayed_files[$handle] = true;
		}
	}
	
	function assign_display($handle, $template_var = '', $return_content = true, $include_once = false)
	{
		$output = '';
		
		if(!$include_once || !isset($this->displayed_files[$handle]))
		{
			$this->_setup_block_vars();
			$this->smarty->assign_by_ref('_user', $user);
			$output = $this->smarty->fetch($this->_files[$handle]);
			if(!empty($template_var))
			{
				$this->smarty->assign($template_var, $output);
			}
			$this->displayed_files[$handle] = true;
		}
		
		return ($return_content) ? $output : true;
	}
	
	function assign_vars($vararray)
	{
		$this->smarty->assign($vararray);
	}
	
	function assign_var($varname, $varval)
	{
		$this->smarty->assign($varname, $varval);
	}
	
	function assign_block_vars($blockname, $vararray)
	{
		$parts = explode('.', $blockname);
		
		$parent = $parts;
		$child = array_pop($parent);
		$parent = implode('.', $parent);
		
		if (empty($parent))
		{
			$block_count = 0;
			if(isset($this->_block_vars[$blockname]))
			{
				$block_count = sizeof($this->_block_vars[$blockname]);
				$this->_last_blocks[$blockname]['S_LAST_ROW'] = false;
			}
			else
			{
				$this->_block_vars[$blockname] = array();
				$vararray['S_FIRST_ROW'] = true;
			}
			$this->_block_vars[$blockname][$block_count] = $vararray;
			$this->_last_blocks[$blockname] = &$this->_block_vars[$blockname][$block_count];
			$this->_last_blocks[$blockname]['S_LAST_ROW'] = true;
		}
		else if(isset($this->_last_blocks[$parent]))
		{
			$block_count = 0;
			if(isset($this->_last_blocks[$parent][$child]))
			{
				$block_count = sizeof($this->_last_blocks[$parent][$child]);
				$this->_last_blocks[$blockname]['S_LAST_ROW'] = false;
			}
			else
			{
				$this->_last_blocks[$parent][$child] = array();
				$vararray['S_FIRST_ROW'] = true;
			}
			$this->_last_blocks[$parent][$child][$block_count] = $vararray;
			$this->_last_blocks[$blockname] = $this->_last_blocks[$parent][$child][$block_count];
			$this->_last_blocks[$blockname]['S_LAST_ROW'] = true;
		}
	}
	
	function _setup_block_vars()
	{
		$keys = array_keys($this->_block_vars);
		foreach($keys as $k)
		{
			$this->smarty->assign_by_ref($k, $this->_block_vars[$k]);
		}
	}
}
