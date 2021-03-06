<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );
require_once( 'helper.php' );

/******************************************************************************************
 *
 * Vote Tracker
 *
 ******************************************************************************************/
class wbVoteTrackerPlugin extends MantisPlugin  {

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
	function register( ) {

    // Plugin
      $this->name        = lang_get( 'plugin_wbvotetracker_title' );
      $this->description = lang_get( 'plugin_wbvotetracker_description' );
      $this->page        = 'config';
  		$this->version = '1.1';
  		$this->requires = array(
  			'MantisCore' => '1.2.19',
  		);
  		$this->author 	= 'David Hunt, Webuddha.com';
  		$this->contact 	= 'mantisbt-dev@webuddha.com';
  		$this->url 			= 'http://www.webuddha.com';

    // Thresholds
      $GLOBALS['g_vote_bug_threshold']                = REPORTER; // Vote
      $GLOBALS['g_vote_add_others_bug_threshold']     = MANAGER;  // Vote for another user

    // Database Table
      $GLOBALS['g_db_table']['mantis_bug_vote_table'] = '%db_table_prefix%_bug_vote%db_table_suffix%';

    // Event Defenitions
      event_declare( 'EVENT_LAYOUT_RIGHT_COLUMN', EVENT_TYPE_DEFAULT );

    // Constants
      define( 'BUG_VOTE', 30 );
      define( 'WBVOTE_CATEGORY_FEATURES', 1 );
      define( 'WBVOTE_CATEGORY_BUGS', 2 );

    // Redirect My Page to Dashboard
      if( strpos( $_SERVER['REQUEST_URI'], 'my_view_page.php' ) !== false ){
        header('Location: plugin.php?page=wbVoteTracker/dashboard');
      }

    // Initialize User Functions
      $this->initUser();

	}

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function initUser(){

    // Extract Globals
      foreach( array_keys($GLOBALS) AS $key ){
        ${ $key } =& $GLOBALS[ $key ];
      }

    // Require
      require_once( 'authentication_api.php' );
      require_once( 'current_user_api.php' );

    // Move globals into global space
      $defined_vars = get_defined_vars();
      foreach( array_keys($defined_vars) AS $key ){
        if( strpos($key, 'g_') === 0 && !isset($GLOBALS[ $key ]) ){
          $GLOBALS[ $key ] = ${ $key };
        }
      }

  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function config() {
    return array(
      'filterCategories' => array()
    );
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function events() {
    return array(
      'EVENT_MENU_MAIN_FRONT' => EVENT_TYPE_FIRST
      );
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function hooks() {
    return array(
      'EVENT_MENU_ISSUE'           => 'EVENT_MENU_ISSUE',
      'EVENT_LAYOUT_RIGHT_COLUMN'  => 'EVENT_LAYOUT_RIGHT_COLUMN',
      'EVENT_MENU_MAIN_FRONT'      => 'EVENT_MENU_MAIN_FRONT',
      'EVENT_LAYOUT_RESOURCES'     => 'EVENT_LAYOUT_RESOURCES',
      'EVENT_LAYOUT_CONTENT_BEGIN' => 'EVENT_LAYOUT_CONTENT_BEGIN',
      'EVENT_LAYOUT_CONTENT_END'   => 'EVENT_LAYOUT_CONTENT_END',
      'EVENT_LAYOUT_BODY_BEGIN'    => 'EVENT_LAYOUT_BODY_BEGIN',
      'EVENT_LAYOUT_BODY_END'      => 'EVENT_LAYOUT_BODY_END',
      'EVENT_REPORT_BUG'           => 'EVENT_REPORT_BUG'
    );
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
	function schema(){

    global $g_db;

    // Push Plugin
    plugin_push_current('wbVoteTracker');

		// schema version\
    $schema = array();
    $schema_count = (int)plugin_config_get( 'schema', -1, false, ALL_USERS, ALL_PROJECTS );

		/* v100 */
      if( $schema_count < 4 ){
        $schema[] = Array(
            'CreateTableSQL',
            Array(
              db_get_table( 'mantis_bug_vote_table' ),
              "
                id I UNSIGNED NOTNULL PRIMARY AUTOINCREMENT,
                bug_id I UNSIGNED NOTNULL,
                user_id I UNSIGNED NOTNULL,
                date_created I UNSIGNED NOTNULL
              ",
              Array(
                'mysql' => 'ENGINE=MyISAM DEFAULT CHARSET=utf8',
                'pgsql' => 'WITHOUT OIDS'
              )
            )
          );
        $schema[] = Array( 'CreateIndexSQL', Array( 'idx_bug_vote_bug_id', db_get_table( 'mantis_bug_vote_table' ), 'bug_id' ) );
        $schema[] = Array( 'CreateIndexSQL', Array( 'idx_bug_vote_user_id', db_get_table( 'mantis_bug_vote_table' ), 'user_id' ) );
        $schema[] = Array( 'AddColumnSQL', Array( db_get_table( 'mantis_bug_table' ), "vote_count I UNSIGNED NOTNULL DEFAULT '0'"));
      }

    /* Apply Schema */
      if( count($schema) ){
        for( $i=0; $i < count($schema); $i++ ){
          $schema_line = $schema[$i];
          $dict = NewDataDictionary( $g_db );
          $t_target = $schema_line[1][0];
          if( isset( $schema_line[2] ) ) {
            if( call_user_func_array( $schema_line[2][0], $schema_line[2][1] ) ) {
              $sqlarray = call_user_func_array( Array( $dict, $schema_line[0] ), $schema_line[1] );
            } else {
              $sqlarray = array();
            }
          } else {
            $sqlarray = call_user_func_array( Array( $dict, $schema_line[0] ), $schema_line[1] );
          }
          $ret = $dict->ExecuteSQLArray( $sqlarray, false );
          if( $ret != 2 ){
            echo '<div class="message error">'. $g_db->ErrorMsg() . "</div>";
          }
          $schema_count++;
        }
        plugin_config_set( 'schema', $schema_count, NO_USER, ALL_PROJECTS  );
      }

	}

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function bug_vote( $p_bug_id, $p_user_id ) {
    $c_bug_id = (int) $p_bug_id;
    $c_user_id = (int) $p_user_id;

    # Don't let the anonymous user monitor bugs
    if ( user_is_anonymous( $c_user_id ) ) {
      return false;
    }

    $t_bug_vote_table = db_get_table( 'mantis_bug_vote_table' );

    # Check History
    $result = db_query_bound(
      "SELECT COUNT(*) AS `total` FROM ".$t_bug_vote_table." WHERE bug_id = ".db_param()." AND user_id = ".db_param(),
      Array( $c_bug_id, $c_user_id )
      );
    $row = db_fetch_array( $result );

    # Only if Not Previous
    if( $row['total'] <= 0 ){

      # Insert vote record
      $result = db_query_bound(
        "INSERT INTO ".$t_bug_vote_table." ( bug_id, user_id, date_created ) VALUES (".db_param().",".db_param().",".db_param().")",
        Array( $c_bug_id, $c_user_id, db_now() )
        );
      if( $result ){
        $t_bug_table = db_get_table( 'mantis_bug_table' );
        db_query_bound("
          UPDATE ".$t_bug_table."
          SET `vote_count` = `vote_count` + 1
          WHERE `id` = ".db_param()
          , Array( $c_bug_id )
          );
      }

      # log new monitoring action
      history_log_event_special( $c_bug_id, BUG_VOTE, $c_user_id );

      # updated the last_updated date
      bug_update_date( $p_bug_id );

      # send email
      wbVoteTrackerPlugin::email_vote( $p_bug_id, $p_user_id );

      return true;
    }

    return false;
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function html_button_vote( $p_bug_id, $t_bug=null, $t_params=array() ) {
    $html = array();
    if( access_has_bug_level( config_get( 'monitor_bug_threshold' ), $p_bug_id ) ) {
      if( !is_object($t_bug) || !isset($t_bug->voted_byuser) ){
        $t_logged_in_user_id = auth_get_current_user_id();
        if( $t_bug->reporter_id == $t_logged_in_user_id ){
          $allowVote = false;
        }
        else {
          $t_bug_vote_table = db_get_table( 'mantis_bug_vote_table' );
          $result = db_query_bound(
            "SELECT COUNT(*) AS `total` FROM ".$t_bug_vote_table." WHERE bug_id = ".db_param()." AND user_id = ".db_param(),
            Array( $p_bug_id, $t_logged_in_user_id )
          );
          $row = db_fetch_array( $result );
          $allowVote = ($row['total'] <= 0);
        }
      } else
        $allowVote = ((int)$t_bug->voted_byuser ? false : true);
      // access_has_global_level( ADMINISTRATOR ) ||
      $t_fields = array( 'bug_id' => $p_bug_id );
      $t_fields = (isset($t_params['fields']) ? array_merge($t_fields,$t_params['fields']) : $t_fields);
      if( $allowVote )
        $html[] = wbVoteTrackerPlugin::html_button( 'bug_vote_add', 'Vote', $t_fields, 'post', array('class'=>'btn_vote') );
      else
        $html[] = wbVoteTrackerPlugin::html_button( 'bug_vote_add', 'Vote', $t_fields, 'post', array('class'=>'btn_vote','extra'=>'disabled="disabled"') );
    }
    return implode("\n", $html);
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function html_button( $p_page, $p_button_text, $p_fields = null, $p_method = 'post', $p_params=array() ) {
    $html = array();
    $p_button_text = string_attribute( $p_button_text );
    if( null === $p_fields ) $p_fields = array();
    if( utf8_strtolower( $p_method ) == 'get' )
      $t_method = 'get';
    else
      $t_method = 'post';
    $html[] = '<form method="'. $t_method .'" action="'. plugin_page($p_page) .'">' . "\n";
    # Add a CSRF token only when the form is being sent via the POST method
    if ( $t_method == 'post' )
      $html[] = form_security_field( $p_page );
    foreach( $p_fields as $key => $val ) {
      $key = string_attribute( $key );
      $val = string_attribute( $val );
      $html[] = "  <input type=\"hidden\" name=\"$key\" value=\"$val\" />\n";
    }
    $class = isset($p_params['class']) ? ' '.$p_params['class'] : null;
    $extra = isset($p_params['extra']) ? ' '.$p_params['extra'] : null;
    $html[] = "  <input type=\"submit\" class=\"button{$class}\" value=\"$p_button_text\" {$extra} />\n";
    $html[] = "</form>\n";
    return implode("\n", $html);
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function email_vote( $p_bug_id, $p_user_id ) {

    log_event( LOG_EMAIL, sprintf( 'Issue #%d voted on by user @U%d', $p_bug_id, $p_user_id ) );
    $t_opt = array();
    $t_opt[] = bug_format_id( $p_bug_id );
    $t_opt[] = user_get_name( $p_user_id );
    email_generic( $p_bug_id, 'monitor', 'email_notification_title_for_action_vote', $t_opt, array( $p_user_id ) );

  }

  /***************************************************************************
   *
   * Plugin CSS / JS includes
   *
   ***************************************************************************/
  function EVENT_LAYOUT_RESOURCES( $event ) {

    // Abort if not plugin
    if( !preg_match('/wbVoteTracker/',gpc_get_string('page', '')) ) return;

    // Add stylesheet
    echo '<link rel="stylesheet" type="text/css" href="'. helper_mantis_url('plugins/wbVoteTracker/files/default.css') .'"/>';

  }

  /***************************************************************************
   *
   * Add vote total / button to view page
   *
   ***************************************************************************/
  function EVENT_MENU_ISSUE( $event, $f_bug_id ){
    $tpl_bug = bug_get( $f_bug_id, true );
    $tpl_bug_is_readonly = bug_is_readonly( $f_bug_id );
    $html = array();
    $html[] = '<div class="votes" style="display:inline;font-weight:bold;">'. $tpl_bug->vote_count .' Vote'.($tpl_bug->vote_count != 1 ? 's' : '').'</div>';
    if( !$tpl_bug_is_readonly )
      $html[] = wbVoteTrackerPlugin::html_button_vote( $f_bug_id );
    return implode("\n", $html);
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_LAYOUT_RIGHT_COLUMN( $event, $script_name ) {

    // Abort if not plugin
    if( !preg_match('/wbVoteTracker/',gpc_get_string('page', '')) ) return;

    // Current Project
    $current_project_id = (int)helper_get_current_project();
    $t_bug_table_where = ($current_project_id ? "`project_id` = '{$current_project_id}'" : "`project_id` > 0");

    // Load Project Record
    if( $current_project_id ){
      $t_project_table = db_get_table( 'mantis_project_table' );
      $result = db_query_bound("SELECT * FROM ".$t_project_table." WHERE `id` = '{$current_project_id}'",Array());
      $project_row = db_fetch_array( $result );
    }

    // Render Sidebar
    ob_start();
    ?>
    <div class="rColBox stats">
      <h3><?php
        if( $project_row ){
          echo sprintf( lang_get( 'plugin_wbvotetracker_statistics_header_project' ), $project_row['name'] );
        }
        else {
          echo lang_get( 'plugin_wbvotetracker_statistics_header_all' );
        }
      ?></h3>
      <div class="boxRow">
        <b><?php
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where,Array());
          $row = db_fetch_array( $result ); echo $row['total'];
        ?></b>
        <span><?php echo lang_get( 'plugin_wbvotetracker_statistics_label_total' ); ?></span>
      </div>
      <div class="boxRow">
        <b><?php
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `status` NOT IN (".db_param().",".db_param().")",Array( RESOLVED, CLOSED ));
          $row = db_fetch_array( $result ); echo $row['total'];
        ?></b>
        <span><?php echo lang_get( 'plugin_wbvotetracker_statistics_label_total_active' ); ?></span>
      </div>
      <div class="boxRow">
        <b><?php
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `status` IN (".db_param().",".db_param().",".db_param().") AND `category_id` IN (".db_param().")",Array( RESOLVED, IMPLEMENTED, CLOSED, WBVOTE_CATEGORY_BUGS ));
          $row = db_fetch_array( $result ); echo $row['total'];
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `category_id` IN (".db_param().")",Array( WBVOTE_CATEGORY_BUGS ));
          $row = db_fetch_array( $result ); echo ' of '.$row['total'];
        ?></b>
        <span><?php echo lang_get( 'plugin_wbvotetracker_statistics_label_total_bugs_resolved' ); ?></span>
      </div>
      <div class="boxRow">
        <b><?php
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `status` IN (".db_param().",".db_param().",".db_param().") AND `category_id` IN (".db_param().")",Array( RESOLVED, IMPLEMENTED, CLOSED, WBVOTE_CATEGORY_FEATURES ));
          $row = db_fetch_array( $result ); echo $row['total'];
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `category_id` IN (".db_param().")",Array( WBVOTE_CATEGORY_FEATURES ));
          $row = db_fetch_array( $result ); echo ' of '.$row['total'];
        ?></b>
        <span><?php echo lang_get( 'plugin_wbvotetracker_statistics_label_total_features_resolved' ); ?></span>
      </div>
      <div class="boxRow">
        <b><?php
          $t_bug_table = db_get_table( 'mantis_bug_table' );
          $result = db_query_bound("SELECT COUNT(*) AS `total` FROM ".$t_bug_table." WHERE ".$t_bug_table_where." AND `status` IN (".db_param().")",Array( CLOSED ));
          $row = db_fetch_array( $result ); echo $row['total'];
        ?></b>
        <span><?php echo lang_get( 'plugin_wbvotetracker_statistics_label_total_closed' ); ?></span>
      </div>
    </div>
    <?php
      $t_current_user_id = auth_get_current_user_id();
      if( $t_current_user_id ){
        $userRow = user_get_row( $t_current_user_id );
        ?>
        <div class="rColBox user">
          <h3>Welcome <?php echo $userRow['realname'] ?></h3>
          <?php
          if( !current_user_is_anonymous() ){
            ?>
            <ul>
              <li><a href="<?php echo plugin_page('dashboard') ?>">Dashboard</a>
              <li><a href="<?php echo plugin_page('browse') ?>&reset=true&category=Features&reporter_id[]=-1">My Features</a>
              <li><a href="<?php echo plugin_page('browse') ?>&reset=true&category=Bugs&reporter_id[]=-1">My Bugs</a>
              <li><a href="<?php echo helper_mantis_url( 'account_page.php' ) ?>"><?php echo lang_get( 'account_link' ) ?></a>
              <li><a href="<?php echo helper_mantis_url( 'account_prefs_page.php' ) ?>"><?php echo lang_get( 'change_preferences_link' ) ?></a>
              <li><a href="<?php echo helper_mantis_url( 'account_prof_menu_page.php' ) ?>"><?php echo lang_get( 'manage_profiles_link' ) ?></a>
            </ul>
            <?php
          }
          ?>
        </div>
        <?php
      }
    ?>
    <?php if( last_visited_enabled() ) { ?>
      <div class="rColBox visited"><?php
        $t_ids = last_visited_get_array();
        echo '<h3>'.lang_get( 'recently_visited' ) . '</h3>';
        $t_first = true;
        foreach( $t_ids as $t_id ) {
          if( !$t_first ) echo ', ';
            else $t_first = false;
          echo string_get_bug_view_link( $t_id );
        }
      ?></div>
    <?php } ?>
    <?php
    echo ob_get_clean();
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_MENU_MAIN_FRONT(){
    $menu = array(
      '<a class="dashboard" href="'. plugin_page( 'dashboard' ) .'">Dashboard</a>',
      '<a class="suggestions" href="'. plugin_page( 'browse' ) .'&reset=true&category=Features">Features</a>',
      '<a class="bugs" href="'. plugin_page( 'browse' ) .'&reset=true&category=Bugs">Bugs</a>'
      );
    if( current_user_get_access_level() == VS_PUBLIC )
      $menu[] = '<a class="login_report" href="'. helper_mantis_url( 'login_page.php' ) .'">'. lang_get( 'report_bug_link' ) .'</a>';
    return $menu;
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_LAYOUT_CONTENT_BEGIN(){

    // Abort if not plugin
    if( !preg_match('/wbVoteTracker/',gpc_get_string('page', '')) ) return;

    // Open Wrapper
    echo '<div class="centercol wbVoteTracker '.(string)preg_replace('/^.*\/(.*?)$/','$1',preg_replace('/\.php$/','',$_SERVER['SCRIPT_NAME'])).'">', "\n";
    echo '  <div class="row">', "\n";
    echo '    <div class="col right">', "\n";
    event_signal( 'EVENT_LAYOUT_RIGHT_COLUMN', array((string)preg_replace('/^.*\/(.*?)$/','$1',preg_replace('/\.php$/','',$_SERVER['SCRIPT_NAME']))) );
    echo '    </div>', "\n";
    echo '    <div class="col center">', "\n";

  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_LAYOUT_CONTENT_END(){

    // Abort if not plugin
    if( !preg_match('/wbVoteTracker/',gpc_get_string('page', '')) ) return;

    // Close Wrapper
    echo '    </div>', "\n";
    echo '  </div>', "\n";
    echo '</div>', "\n";

  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_LAYOUT_BODY_BEGIN(){
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_LAYOUT_BODY_END(){
  }

  /***************************************************************************
   *
   *
   *
   ***************************************************************************/
  function EVENT_REPORT_BUG( $event, $t_bug_data, $t_bug_id ){

    // Vote Option
      $voteOnReportBug = plugin_config_get( 'voteOnReportBug', -1, false, $t_bug_data->reporter_id, $t_bug_data->project_id );
      if( $voteOnReportBug < 0 ){
        $voteOnReportBug = 1;
        plugin_config_set( 'voteOnReportBug', $voteOnReportBug, NO_USER, ALL_PROJECTS  );
      }

    // Vote
      if( $voteOnReportBug ){
        $this->bug_vote( $t_bug_data->id, $t_bug_data->reporter_id );
      }

  }

}
