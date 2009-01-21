<?php
/* ===========================================================================
ext.md_livesearch.php ---------------------------
Adds a live search to the EE control panel header.
            
INFO ---------------------------
Created:   Sep 06 2006 (Mark Huot, hcphilly.com)
Last Mod:  Jan 04 2009

Original:
Related Thread: http://expressionengine.com/forums/viewthread/38361/

This version:
Related Thread: http://expressionengine.com/forums/viewthread/---/ none yet
		
http://expressionengine.com/docs/development/extensions.html
=============================================================================== */
/* The following line needs to be in most extesnions, however, I could not get the
search results to show when it was put in. So, commented out for now. */
// if ( ! defined('EXT')) { exit('Invalid file request'); }

if ( ! defined('MD_LS_version')){
	define("MD_LS_version",			"1.1.8");
	define("MD_LS_docs_url",		"http://www.masugadesign.com/the-lab/scripts/md-live-search/");
	define("MD_LS_addon_id",		"MD Live Search");
	define("MD_LS_extension_class",	"Md_livesearch");
	define("MD_LS_cache_name",		"mdesign_cache");
	
	define("MD_Ext_Filename",		"ext.md_livesearch.php");
}

if(isset($_GET['ls_get_query']))
  {
    Md_livesearch::LiveSearchResults($_GET['ls_get_query']);
  }

class Md_livesearch {

  var $settings       = array();
  var $name           = 'MD Live Search';
  // var $type        = ''; only used for custom field types
  var $version        = MD_LS_version;
  var $description    = 'Adds a live search to the top of every CMS page.';
  var $settings_exist = 'y';
  var $docs_url       = MD_LS_docs_url;

  var $js_heading_set = false;

// --------------------------------
//  PHP 4 Constructor
// --------------------------------
  function Md_livesearch($settings='')
  {
    $this->__construct($settings);
  }

// --------------------------------
//  PHP 5 Constructor
// --------------------------------
	function __construct($settings='')
	{
		global $IN, $SESS;
		if(isset($SESS->cache['mdesign']) === FALSE){ $SESS->cache['mdesign'] = array();}
		$this->settings = $this->_get_settings();
		$this->debug = $IN->GBL('debug');
	}

	function _get_settings($force_refresh = FALSE, $return_all = FALSE)
	{
		global $SESS, $DB, $REGX, $LANG, $PREFS;

		// assume there are no settings
		$settings = FALSE;

		// Get the settings for the extension
		if(isset($SESS->cache['mdesign'][MD_LS_addon_id]['settings']) === FALSE || $force_refresh === TRUE)
		{
			// check the db for extension settings
			$query = $DB->query("SELECT settings FROM exp_extensions WHERE enabled = 'y' AND class = '" . MD_LS_extension_class . "' LIMIT 1");

			// if there is a row and the row has settings
			if ($query->num_rows > 0 && $query->row['settings'] != '')
			{
				// save them to the cache
				$SESS->cache['mdesign'][MD_LS_addon_id]['settings'] = $REGX->array_stripslashes(unserialize($query->row['settings']));
			}
		}

		// check to see if the session has been set
		// if it has return the session
		// if not return false
		if(empty($SESS->cache['mdesign'][MD_LS_addon_id]['settings']) !== TRUE)
		{
			$settings = ($return_all === TRUE) ?  $SESS->cache['mdesign'][MD_LS_addon_id]['settings'] : $SESS->cache['mdesign'][MD_LS_addon_id]['settings'][$PREFS->ini('site_id')];
		}
		return $settings;
	}

	function settings_form($current)
	{
		global $DB, $DSP, $LANG, $IN, $PREFS, $SESS;

		// create a local variable for the site settings
		$settings = $this->_get_settings();

		$DSP->crumbline = TRUE;

		$DSP->title  = $LANG->line('extension_settings');
		$DSP->crumb  = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities')).
		$DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')));

		$DSP->crumb .= $DSP->crumb_item($LANG->line('extension_title') . " {$this->version}");

		$DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

		$DSP->body = '';
		$DSP->body .= $DSP->heading($LANG->line('extension_title') . " <small>{$this->version}</small>");
		$DSP->body .= $DSP->form_open(
								array(
									'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings'
								),
								array('name' => strtolower(MD_LS_extension_class))
		);
	
	// EXTENSION ACCESS
	$DSP->body .= $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%;'));

	$DSP->body .= $DSP->tr()
		. $DSP->td('tableHeading', '', '2')
		. $LANG->line("access_rights")
		. $DSP->td_c()
		. $DSP->tr_c();

	$DSP->body .= $DSP->tr()
		. $DSP->td('tableCellOne', '30%')
		. $DSP->qdiv('defaultBold', $LANG->line('enable_extension_for_this_site'))
		. $DSP->td_c();

	$DSP->body .= $DSP->td('tableCellOne')
		. "<select name='enable'>"
		. $DSP->input_select_option('y', "Yes", (($settings['enable'] == 'y') ? 'y' : '' ))
		. $DSP->input_select_option('n', "No", (($settings['enable'] == 'n') ? 'y' : '' ))
		. $DSP->input_select_footer()
		. $DSP->td_c()
		. $DSP->tr_c()
		. $DSP->table_c();

    // SEARCH AREAS
		$DSP->body .= $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%;'));

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableHeading', '', '2')
			. $LANG->line("livesearch_areas_title")
			. $DSP->td_c()
			. $DSP->tr_c();
			
		$DSP->body .= $DSP->tr()
			. $DSP->td('', '', '2')
			. "<div class='box' style='border-width:0 0 1px 0; margin:0; padding:10px 5px'><p>" . $LANG->line('livesearch_areas_desc'). "</p></div>"
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line('include_entries_label'))
			. $DSP->td_c();

	    $DSP->body .= $DSP->td('tableCellOne')
			. "<select name='include_entries'>"
			. $DSP->input_select_option('y', "Yes", (($settings['include_entries'] == 'y') ? 'y' : '' ))
			. $DSP->input_select_option('n', "No", (($settings['include_entries'] == 'n') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();
			

		// MAX ENTRIES
		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('default', $LANG->line('include_entries_max_label'))
			. $DSP->td_c();
			
	    $DSP->body .= $DSP->td('tableCellOne')
			. $DSP->input_text('max_entries', $settings['max_entries'], '35', '40', 'input', '50px')
			. $DSP->td_c()
			. $DSP->tr_c();
			

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line('include_comments_label'))
			. $DSP->td_c();

	    $DSP->body .= $DSP->td('tableCellOne')
			. "<select name='include_comments'>"
			. $DSP->input_select_option('y', "Yes", (($settings['include_comments'] == 'y') ? 'y' : '' ))
			. $DSP->input_select_option('n', "No", (($settings['include_comments'] == 'n') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();
			
		// MAX COMMENTS
		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('default', $LANG->line('include_comments_max_label'))
			. $DSP->td_c();
			
	    $DSP->body .= $DSP->td('tableCellOne')
			. $DSP->input_text('max_comments', $settings['max_comments'], '35', '40', 'input', '50px')
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->table_c();

		// SORT PREFS
		$DSP->body .= $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%;'));

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableHeading', '', '2')
			. $LANG->line("sort_prefs_label")
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line("sort_by_label"))
			. $DSP->td_c();

		$DSP->body .= $DSP->td('tableCellOne')
			. "<select name='sort_by'>"
			. $DSP->input_select_option('DATE', "DATE", (($settings['sort_by'] == 'DATE') ? 'y' : '' ))
			. $DSP->input_select_option('TITLE', "TITLE", (($settings['sort_by'] == 'TITLE') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();
			
		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line("sort_order_label"))
			. $DSP->td_c();
			
		$DSP->body .= $DSP->td('tableCellOne')
			. "<select name='sort_order'>"
			. $DSP->input_select_option('ASC', "ASC", (($settings['sort_order'] == 'ASC') ? 'y' : '' ))
			. $DSP->input_select_option('DESC', "DESC", (($settings['sort_order'] == 'DESC') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();

	$DSP->body .= $DSP->table_c();

		// DISPLAY PREFS
		$DSP->body .= $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%;'));

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableHeading', '', '2')
			. $LANG->line("display_prefs_label")
			. $DSP->td_c()
			. $DSP->tr_c();
		
		// DISPLAY DATE
		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line("display_date_label"))
			. $DSP->td_c();

		$DSP->body .= $DSP->td('tableCellOne')
			. "<select name='display_date'>"
			. $DSP->input_select_option('y', "Yes", (($settings['display_date'] == 'y') ? 'y' : '' ))
			. $DSP->input_select_option('n', "No", (($settings['display_date'] == 'n') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();

		// DISPLAY STATUS
		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line('display_status_label'))
			. $DSP->td_c();
			
	    $DSP->body .= $DSP->td('tableCellOne')
			. "<select name='display_status'>"
			. $DSP->input_select_option('y', "Yes", (($settings['display_status'] == 'y') ? 'y' : '' ))
			. $DSP->input_select_option('n', "No", (($settings['display_status'] == 'n') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();

		
		$DSP->body .= $DSP->table_c();

		
		// CSS
		$DSP->body .=   $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%'));

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableHeading', '', '2')
			. $LANG->line("css_title")
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('default', $LANG->line('css_info'))
			. $DSP->td_c();

		$DSP->body .= $DSP->td('tableCellOne')
			. $DSP->input_textarea('css', $settings['css'], 16, 'textarea', '99%')
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .=   $DSP->table_c();


		// UPDATES
		$DSP->body .= $DSP->table_open(array('class' => 'tableBorder', 'border' => '0', 'style' => 'margin-top:18px; width:100%;'));

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableHeading', '', '2')
			. $LANG->line("check_for_updates_title")
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->tr()
			. $DSP->td('', '', '2')
			. "<div class='box' style='border-width:0 0 1px 0; margin:0; padding:10px 5px'><p>" . $LANG->line('check_for_updates_info') . "</p></div>"
			. $DSP->td_c()
			. $DSP->tr_c();

		$DSP->body .= $DSP->tr()
			. $DSP->td('tableCellOne', '40%')
			. $DSP->qdiv('defaultBold', $LANG->line("check_for_updates_label"))
			. $DSP->td_c();

		$DSP->body .= $DSP->td('tableCellOne')
			. "<select name='check_for_updates'>"
			. $DSP->input_select_option('y', "Yes", (($settings['check_for_updates'] == 'y') ? 'y' : '' ))
			. $DSP->input_select_option('n', "No", (($settings['check_for_updates'] == 'n') ? 'y' : '' ))
			. $DSP->input_select_footer()
			. $DSP->td_c()
			. $DSP->tr_c();
			
		$DSP->body .= $DSP->table_c();

	

		$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit("Submit"))
					. $DSP->form_c();
	}

	function save_settings()
	{
		global $DB, $IN, $OUT, $PREFS, $REGX, $SESS;

		// create a default settings array
		$default_settings = array();

		// merge the defaults with our $_POST vars
		$site_settings = array_merge($default_settings, $_POST);

		// unset the name
		unset($site_settings['name']);

		// load the settings from cache or DB
		// force a refresh and return the full site settings
		$settings = $this->_get_settings(TRUE, TRUE);

		// add the posted values to the settings
		$settings[$PREFS->ini('site_id')] = $site_settings;

		// update the settings
		$query = $DB->query($sql = "UPDATE exp_extensions SET settings = '" . addslashes(serialize($settings)) . "' WHERE class = '" . get_class($this) . "'");

		$this->settings = $settings[$PREFS->ini('site_id')];

		if($this->settings['enable'] == 'y')
		{
			if (session_id() == "") session_start(); // if no active session we start a new one
		}
	}
	
	
	// --------------------------------
	//  Activate Extension
	// --------------------------------
	function activate_extension ()
	{
        global $DB, $PREFS, $SESS;
$css = '
#livesearch-query {
	background: #26333B;
	font-weight: bold;
	padding: 5px;
	border: 1px solid #3D525F;
	color: #45A7DC;
	}

.grey {
	color: #999;
	}

#livesearch-results li ol
	{
	margin: 0;
	padding: 0;
	list-style-type: none;
	}

#livesearch-results li ol li a:link,
#livesearch-results li ol li a:visited
	{
	color: #1D7FC6;
	display: block;
	padding: 5px;
	}

#livesearch-results li ol li a:hover {
	background: #EEF4F9;
	}

.livesearch-results_weblog_title {
	color: #999;
	font-size: 10px;
	font-weight: bold;
	margin: 2px;
	font-style: normal;
	}

.livesearch-results_entryStatus {
	font-weight: normal;
	font-style: italic;
	}

.livesearch-results_date {
	color: #999;
	font-size: 9px;
	font-weight: normal;
	margin: 2px;
	font-style:	normal;
	}

#livesearch-results {
	position: absolute;
	top: 38px;
	right: 30px;
	width: 210px;
	background:	#fff;
	padding: 0;
	list-style: none;
	text-align: left;
	color: #333;
	border: 1px solid #ccc;
	font-size: 11px;
	}

#livesearch-results div {
	color: #fff;
	font-size: 11px;
	font-weight: bold;
	padding: 7px 6px;
	background:	#768E9D url(/themes/cp_themes/default/images/bg_table_heading.gif) repeat-x scroll left bottom;
	}

#livesearch-results ul#entriesList {
	margin-left: 0;
	margin-top: 0;
	padding-left: 0;
	list-style: none;
	}
';

		$default_settings = array(
		  'include_entries'		=> 'y',
		  'include_comments'	=> 'y',
			'max_entries'				=> 10,
			'max_comments'			=> 10,
			'sort_by'						=> 'DATE',
			'sort_order'				=> 'DESC',
			'display_date'			=> 'y',
			'display_status'		=> 'y',
		  'enable'						=> 'y',
			'check_for_updates'	=> 'y',
			'css'								=> $css
		);

		// get the list of installed sites
		$query = $DB->query("SELECT * FROM exp_sites");

		// if there are sites - we know there will be at least one but do it anyway
		if ($query->num_rows > 0)
		{
			// for each of the sites
			foreach($query->result as $row)
			{
				// build a multi dimensional array for the settings
				$settings[$row['site_id']] = $default_settings;
			}
		}

		$hooks = array(
		  'show_full_control_panel_end'         => 'show_full_control_panel_end',
		  // allow to work with LG Addon Updater
		  'lg_addon_update_register_source'     => 'lg_addon_update_register_source',
		  'lg_addon_update_register_addon'      => 'lg_addon_update_register_addon'
		);
		
		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string( 'exp_extensions', 
				array(
          'extension_id' => '',
          'class'        => get_class($this),
          'method'       => $method,
          'hook'         => $hook,
          'settings'     => addslashes(serialize($settings)),
          'priority'     => 10,
          'version'      => $this->version,
          'enabled'      => "y"
				)
			);
		}

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}
		return TRUE;
	}

	// --------------------------------
	//  Disable Extension
	// -------------------------------- 
	function disable_extension()
	{
		global $DB;
		$DB->query("DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'");
	}
	
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	function update_extension($current='')
	{
		global $DB;
		
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		$DB->query("UPDATE exp_extensions
		            SET version = '".$DB->escape_str($this->version)."'
		            WHERE class = '".get_class($this)."'");
	}
	// END

	function lg_addon_update_register_source($sources)
	{
		global $EXT;
		if($EXT->last_call !== FALSE)
			$sources = $EXT->last_call;
		// must be in the following format:
		/*
		<versions>
			<addon id='LG Addon Updater' version='2.0.0' last_updated="1218852797" docs_url="http://leevigraham.com/" />
		</versions>
		*/
		if($this->settings['check_for_updates'] == 'y')
		{
			$sources[] = 'http://masugadesign.com/versions/';
		}
		return $sources;
	}

	function lg_addon_update_register_addon($addons)
	{
		global $EXT;
		if($EXT->last_call !== FALSE)
			$addons = $EXT->last_call;
		if($this->settings['check_for_updates'] == 'y')
		{
			$addons[MD_LS_addon_id] = $this->version;
		}
		return $addons;
	}
    

    function show_full_control_panel_end( $out )
	  {
		global $EXT, $DSP, $IN, $SESS, $LANG, $PREFS;
		
		if($EXT->last_call !== false)
		{
			$out = $EXT->last_call;
		}
		
		if($this->settings['enable'] == 'y')
		{

		$headstuff= '';	

		$ext_url = $PREFS->core_ini['site_url'].$PREFS->core_ini['system_folder'].'/extensions/'. MD_Ext_Filename .'?ls_get_query=';
	
		$js = '<script type="text/javascript" charset="utf-8">var SESSION="'.$IN->GBL('S').'";</script>';		
		$js.= '<script type="text/javascript" charset="utf-8">var extension_url = "'.$ext_url.'";</script>'.NL;

		//$headstuff .= $js;	
		
ob_start();
?>
<script type="text/javascript" charset="utf-8">

	function addLoadEvent(func) {
	  var oldonload = window.onload;
	  if (typeof window.onload != 'function') {
	    window.onload = func;
	  } else {
	    window.onload = function() {
	      if (oldonload) {
	        oldonload();
	      }
	      func();
	    }
	  }
	}
	
	function ajax( url, func )
	{
		req = false;
		// branch for native XMLHttpRequest object
		if(window.XMLHttpRequest) {
			try {
				req = new XMLHttpRequest();
			} catch(e) {
				req = false;
			}
		// branch for IE/Windows ActiveX version
		} else if(window.ActiveXObject) {
			try {
				req = new ActiveXObject("Msxml2.XMLHTTP");
			} catch(e) {
				try {
					req = new ActiveXObject("Microsoft.XMLHTTP");
				} catch(e) {
					req = false;
				}
			}
		}
		if(req) {
			req.onreadystatechange = func;
			req.open("GET", url, true);
			req.send("");
		}
	}
	
	function delegate( that, thatMethod )
    {
        return function() { return thatMethod.call(that); }
    }
	
	function livesearch()
	{
		document.getElementById("livesearch-query").onfocus = function()
		{
			if(this.value == "Live Search")
			{
			    this.value = "";
			}
			
			this.onkeyup();
		}
		document.getElementById("livesearch-query").onblur = function()
		{
			if(this.value == "")
			{
				this.className = "grey";
				this.value = "Live Search";
			}
			
			if(document.getElementById("livesearch-results"))
			{
				// comment out the following line for inspecting and testing styles
				document.getElementById("livesearch-results").parentNode.removeChild(document.getElementById("livesearch-results"));
				lastValue = '';
			}
		}
		document.getElementById("livesearch-query").onkeyup = keypressed;
	}
	
	var d = new Date();
	var lastTime = d.getTime();
	var returnLocation = false;
	var lastValue = "";
	
	// 13 = enter
	// 38 = up arrow
	// 40 = down arrow
	
	function keypressed(e)
	{
	    d = new Date();

			if(d.getTime() > lastTime+100)
		{
		  code = -1;
			if (!e) var e = window.event;
			if (e.keyCode) code = e.keyCode;
			else if (e.which) code = e.which;

			
			if(code == 13 && returnLocation != false)
			{
				window.location = returnLocation;
			}

			if((code == 38 || code == 40) && document.getElementById("livesearch-results"))
			{
				var lis = new Array();
				var ols = document.getElementById("livesearch-results").getElementsByTagName("ol");
				for(var i=0; i<ols.length; i++)
				{
					for(var j=0; j<ols[i].childNodes.length; j++)
					{
						lis.push(ols[i].childNodes[j]);
					}
				}

				if(code == 38)
				{
					selectNext(lis.reverse());
				}
				else if(code == 40)
				{
					selectNext(lis);
				}
			}
            
      var text = document.getElementById("livesearch-query");
			if(text.value != "" && text.value != lastValue)
			{
				text.className = "";
				ajax(extension_url+text.value, searchresults);
			}
			else if(text.value == "" && document.getElementById("livesearch-results"))
			{
				document.getElementById("livesearch-results").parentNode.removeChild(document.getElementById("livesearch-results"));
			}

			lastValue = text.value;
		}
		lastTime = d.getTime();
	}
	
	function setSelected( li )
	{
		var lis = new Array();
		var ols = document.getElementById("livesearch-results").getElementsByTagName("ol");
		for(var i=0; i<ols.length; i++)
		{
			for(var j=0; j<ols[i].childNodes.length; j++)
			{
				lis.push(ols[i].childNodes[j]);
			}
		}
		
		for(var i=0; i<lis.length; i++)
		{
			if(lis[i] != li)
			{
				lis[i].className = "";
			}
			else
			{
				lis[i].className = "selected";
				returnLocation = lis[i].firstChild.href;
			}
		}
	}
	
	function selectNext( lis )
	{
		var selected = false;
		for(var i=0; i<lis.length; i++)
		{
			if(lis[i].className == "selected" && selected == false)
			{
				lis[i].className = "";
				if(lis[i+1])
				{
					lis[i+1].className = "selected";
					returnLocation = lis[i+1].firstChild.href;
					window.status = returnLocation;
					selected = true;
				}
			}
		}
		
		if(!selected && lis.length > 0)
		{
			lis[0].className = "selected";
			returnLocation = lis[0].firstChild.href;
		}
	}
	
	function searchresults()
	{
		/*
			<ul>			var results
				<li>		var entries
					<ul>	var entries_ul
		*/
		
		if (req.readyState == 4) {
		if (req.status == 200) {
			var dom = req.responseXML;
			
			if(document.getElementById("livesearch-results"))
			{
				document.getElementById("livesearch-results").parentNode.removeChild(document.getElementById("livesearch-results"));
			}
			
			var results = document.createElement("ul");
				results.id = "livesearch-results";
			
			var entry_list = xmlToLi(dom.getElementsByTagName("entries").item(0));
			if(entry_list.length > 0)
			{
				var entries = document.createElement("li");
					results.appendChild(entries);
				var entries_div = document.createElement("div");
					entries.appendChild(entries_div);
				var entries_text = document.createTextNode("Entries");
					entries_div.appendChild(entries_text);
				var entries_ul = document.createElement("ol");
					entries.appendChild(entries_ul);

				for(var i=0; i<entry_list.length; i++)
				{
					entries_ul.appendChild(entry_list[i]);
				}				
			}

			var comment_list = xmlToLi(dom.getElementsByTagName("comments").item(0));
			if(comment_list.length > 0)
			{
				var comments = document.createElement("li");
					results.appendChild(comments);
				var comments_div = document.createElement("div");
					comments.appendChild(comments_div);
				var comments_text = document.createTextNode("Comments");
					comments_div.appendChild(comments_text);
				var comments_ul = document.createElement("ol");
					comments.appendChild(comments_ul);

				for(var i=0; i<comment_list.length; i++)
				{
					comments_ul.appendChild(comment_list[i]);
				}
			}
		
			document.body.appendChild(results);
			
			if(results.getElementsByTagName("ol").length > 0)
			{
				if(results.getElementsByTagName("ol").item(0).getElementsByTagName("li").length > 0)
				{
					setSelected(results.getElementsByTagName("ol").item(0).getElementsByTagName("li").item(0));
				}
			}
		} else {
			// alert("There was a problem retrieving the XML data:\n" + req.statusText);
		}
		}
	}
	
	function xmlToLi( nodeList )
	{
		var nodes = new Array();
		
		if(!nodeList) return nodes;
		
		for(var i=0; i<nodeList.childNodes.length; i++)
		{
			var entry = nodeList.childNodes[i];
			var entry_li = document.createElement("li");
			var comment_id = false;
			var entry_id = false;
			var weblog_id = false;
			var title = false;
			

			//var status = false;
			var highlight = false;
			
			var display_date = false;
			var display_status = false;
			
			var blog_title = false;
			
			if(entry.getElementsByTagName("comment_id").length > 0)
			{
				comment_id = entry.getElementsByTagName("comment_id").item(0).firstChild.nodeValue;
			}
			if(entry.getElementsByTagName("weblog_id").length > 0)
			{
				weblog_id = entry.getElementsByTagName("weblog_id").item(0).firstChild.nodeValue;
			}
			if(entry.getElementsByTagName("entry_id").length > 0)
			{
				entry_id = entry.getElementsByTagName("entry_id").item(0).firstChild.nodeValue;
			}
			if(entry.getElementsByTagName("title").length > 0)
			{
				title = entry.getElementsByTagName("title").item(0).firstChild.nodeValue;
			}
			else
			{
				title = "No Title";
			}
			

			if(entry.getElementsByTagName("blog_title").length > 0)
			{
				blog_title = entry.getElementsByTagName("blog_title").item(0).firstChild.nodeValue;
			}

			if(entry.getElementsByTagName("display_date").length > 0)
			{
				display_date = entry.getElementsByTagName("display_date").item(0).firstChild.nodeValue;
			}
			
			// Status
			if(entry.getElementsByTagName("display_status").length > 0)
			{
				display_status = entry.getElementsByTagName("display_status").item(0).firstChild.nodeValue;
			}
			//
			//if(entry.getElementsByTagName("status").length > 0)
			//{
			//	status = entry.getElementsByTagName("status").item(0).firstChild.nodeValue;
			//}
      //
			if(entry.getElementsByTagName("highlight").length > 0)
			{
				highlight = entry.getElementsByTagName("highlight").item(0).firstChild.nodeValue;
			}
			
			if(weblog_id != false && entry_id != false && title != false && blog_title != false)
			{
				href = "?S="+SESSION+"&C=edit";
				if(comment_id != false) href += "&M=edit_comment";
				else href += "&M=edit_entry";
				href += "&weblog_id="+weblog_id;
				href += "&entry_id="+entry_id;
				if(comment_id != false) href += "&comment_id="+comment_id;
				
				var link = document.createElement("a");
					link.href = href;

					link.innerHTML = title;
					
					//var display_status = true;

				if (display_status != false) {
					link.innerHTML += '<br /><span class="livesearch-results_weblog_title">' + blog_title + ' - <span class="livesearch-results_entryStatus" style="color:#'+highlight+'">'+ display_status + '</span></span>'; 
				} else {
					link.innerHTML += '<br/><span class="livesearch-results_weblog_title">' + blog_title + '</span>'; 
				}

					if (display_date != false)
					  link.innerHTML += '<br/><span class="livesearch-results_date">' + display_date + '</span>'; 
					
				
				entry_li.appendChild(link);
				
				entry_li.onmouseover = function()
				{
					setSelected(this);
				}
				entry_li.onmousedown = function()
				{
					window.location = this.firstChild.href;
				}
			}
			nodes.push(entry_li);
		}
		
		return nodes;
	}
	
	addLoadEvent(livesearch);
</script>
<?php
$thejs .= ob_get_contents();
ob_end_clean();
		
		$out .= $js;
		$out .= $thejs;
	

	
		$new_styles = $this->settings['css']; 
		$headstuff .= '<style type="text/css" media="screen">'.$new_styles.'</style>';

			// this stuff needs to go at the bottom to avoid conflicts
			//$out = str_replace("</body>", $fireup . "</body>", $out);
			$out = str_replace("</head>", $headstuff . "</head>", $out);

		$xhtml = "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<span id=\"livesearch\"><input type=\"text\" accesskey=\"l\" id=\"livesearch-query\" class=\"grey\" name=\"livesearch-query\" value=\"Live Search\" style=\"font-size:7pt;width:200px;\" /></span><div id=\"livesearch-results_div\"></div>\r";
		$out = preg_replace("/(>".$LANG->line('new_tab')."<\/a>)/", "$1".$xhtml, $out);

	}
		
		return $out;
	}
	

	function LiveSearchResults()
	  {
	    //global $DB, $DSP, $LANG, $IN, $PREFS, $SESS;
				
	  define('EXT', 'tricky');
		require_once("../config.php");
		header("Content-type:text/xml");
			
		$livequery = $_GET["ls_get_query"];

		$results = array("entries" => 'entry', "comments" => 'comment');
		
		$conn = mysql_connect($conf['db_hostname'], $conf['db_username'], $conf['db_password']);
				mysql_select_db($conf['db_name']);

		$settings_md = mysql_query("SELECT `settings` FROM exp_extensions WHERE enabled = 'y' AND class = 'MD_livesearch' LIMIT 1") or die(mysql_error());
		$settings_md = unserialize(mysql_result($settings_md, 0));
		$settings_md = $settings_md[1];
		

		//	Get searchable fields
		$fields = array();
		$query = mysql_query("SELECT field_id FROM exp_weblog_fields");
		while($field = mysql_fetch_object($query))
		{
			$fields[] = "d.field_id_".$field->field_id;
		}
		
		//print_r($results);
		
		$r  = '<?xml version="1.0" encoding="UTF-8"?>'."\r";
		
		$r .= "<livesearch>\r";
		
		foreach ($results as $resultKey=>$resultValue)
		  {
			if ($settings_md['include_'.$resultKey] == 'y')
			  {		
				// Make max_entries number only, and set to 10 if null or zero.
				$max_results	= ereg_replace("[^0-9]", "", $settings_md['max_'.$resultKey]);
				
				if (strlen($max_results) == 0 || $max_results == 0)
				  {
					$max_results = 10;
				  }
				
				if ($settings_md['sort_order'] == 'ASC')
				  $sort_order = "ASC";
				elseif ($settings_md['sort_order'] == 'DESC')
				  $sort_order = "DESC";
				else
				  $sort_order = "DESC";
				  
				if ($resultKey == "entries")
				{
					  
					# Begin Fix for sort by.
					if ($settings_md['sort_by'] == "DATE")
					{
						$sort_by_type = "t.entry_date";
					}
					elseif ($settings_md['sort_by'] == "TITLE")
					{
					  $sort_by_type = "t.title";
					}
					else
					{
					  $sort_by_type = "t.entry_date";
					}
					
					if ($settings_md['display_date'] == 'y')
					{
					  $display_date = ", t.entry_date AS display_date";
					}
					else
					{
					  $display_date = "";
					}


					$display_status = "";
					$display_status2 = "";
					$display_status3 = "";

					if ($settings_md['display_status'] == 'y')
					{
					  $display_status = "s.status AS display_status, s.highlight,";
						$display_status2 = "exp_statuses AS s,";
						$display_status3 = "AND s.status=t.status";
					}
				

				}
				elseif ($resultKey == "comments")
				{  
					if ($settings_md['sort_by'] == "DATE")
					  $sort_by_type = "c.comment_date";
					elseif ($settings_md['sort_by'] == "TITLE")
					  $sort_by_type = "c.comment";
					else
					  $sort_by_type = "c.comment_date";
					
					if ($settings_md['display_date'] == 'y')
					  $display_date = ", c.comment_date AS display_date";
					else
					  $display_date = "";
				}
				
				else
				{
				    //
				}
				  
				$r .= "\t<$resultKey>\r";
				

				if ($resultKey == "entries")
				  $query = mysql_query("SELECT DISTINCT ".$display_status." t.entry_id, t.weblog_id, t.title, w.blog_title".$display_date." FROM exp_weblog_titles AS t, exp_weblog_data AS d, ".$display_status2." exp_weblogs AS w WHERE t.entry_id=d.entry_id AND t.weblog_id = w.weblog_id ".$display_status3." AND (t.title LIKE '%".mysql_real_escape_string($livequery)."%' OR ".implode(" LIKE '%".mysql_real_escape_string($livequery)."%' OR ", $fields)." LIKE '%".mysql_real_escape_string($livequery)."%') ORDER BY ".$sort_by_type." ".$sort_order." LIMIT ".$max_results."");
				elseif ($resultKey == "comments")
				  $query = mysql_query("SELECT c.comment_id, c.entry_id, c.weblog_id, c.comment AS title, w.blog_title".$display_date." FROM exp_comments AS c, exp_weblogs AS w WHERE c.weblog_id = w.weblog_id AND c.comment LIKE '%".mysql_real_escape_string($livequery)."%' ORDER BY ".$sort_by_type." ".$sort_order." LIMIT ".$max_results."");
				else
				  {
		
				  }
	

				while($entries_or_comments = mysql_fetch_assoc($query))
				{

					$r .= "\t\t<$resultValue>\r";
					
					foreach($entries_or_comments as $ecKey=>$ecValue)
					  {
					    if ($ecKey == "display_date")
							{
								$ecValue = date("Y/m/d g:i A",$ecValue);	 
							}

					    //if ($ecKey == "display_status")
							//{
							//	$ecValue = "foo";	 
							//}

						$r .= "\t\t\t<$ecKey>";
						$r .= (!is_numeric($ecValue)) ? "<![CDATA[": "";
						$r .= (strlen($ecValue) > 25) ? strip_tags(substr(preg_replace("/[\r\n]+\s*/", " / ", $ecValue), 0, 25))."..." : strip_tags($ecValue);
						$r .= (!is_numeric($ecValue)) ? "]]>" : "";
						$r .= "</$ecKey>\r";
					  }
					  
					$r .= "\t\t</$resultValue>\r";
					
				}
				
				$r .= "\t</$resultKey>\r";
				  
			  }
		  }
		
		$r .= "</livesearch>";
		$r = preg_replace("/[\r\n]+\s*/", "", $r);
	
		echo $r;
		
	  }
	
}

?>