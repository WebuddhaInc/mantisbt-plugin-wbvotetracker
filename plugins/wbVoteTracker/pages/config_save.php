<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

html_page_top( lang_get( 'plugin_wbfeaturetracker_header_config' ) );

$t_logged_in_user_id = auth_get_current_user_id();
if ( user_is_anonymous( $t_logged_in_user_id ) ) {

  echo '<br /><div class="center">';
  echo 'Permission Denied.<br />';
  print_bracket_link( 'view.php?id='.$f_bug_id, lang_get( 'proceed' ) );
  echo '</div>';

}

form_security_validate( 'config' );
form_security_purge( 'config' );

$a_config = gpc_get( 'config', array() );
foreach($a_config AS $k => $v)
  plugin_config_set( $k, $v );

print_successful_redirect( plugin_page( 'config', true ) );