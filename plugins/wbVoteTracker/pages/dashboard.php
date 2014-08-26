<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

  $t_current_user_id = auth_get_current_user_id();

  # Improve performance by caching category data in one pass
  category_get_all_rows( helper_get_current_project() );

  compress_enable();

  if ( current_user_get_pref( 'refresh_delay' ) > 0 )
    html_meta_redirect( plugin_page( 'dashboard' ), current_user_get_pref( 'refresh_delay' )*60 );

  $f_page_number = gpc_get_int( 'page_number', 1 );

  $t_per_page   = config_get( 'my_view_bug_count' );
  $t_bug_count  = null;
  $t_page_count = null;

  $t_project_id = helper_get_current_project();
  $t_filter = current_user_get_bug_filter();
  if( $t_filter === false || isset($_REQUEST['reset']) && $_REQUEST['reset'] == 'true' )
    $t_filter = filter_get_default();
  $t_filter['per_page'] = config_get( 'my_view_bug_count' );
  $t_per_page = $t_filter['per_page'];

  $t_sort = $t_filter['sort'];
  $t_dir = $t_filter['dir'];

  $t_icon_path                      = config_get( 'icon_path' );
  $t_update_bug_threshold           = config_get( 'update_bug_threshold' );
  $t_bug_resolved_status_threshold  = config_get( 'bug_resolved_status_threshold' );
  $t_hide_status_default            = config_get( 'hide_status_default' );
  $t_default_show_changed           = config_get( 'default_show_changed' );
  $category_key                     = gpc_get_string( 'category', '' );
  $reporter_id                      = gpc_get_string_array( FILTER_SEARCH_REPORTER_ID, META_FILTER_ANY );

  $c_filter = array(
    FILTER_PROPERTY_CATEGORY => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_SEVERITY_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_STATUS_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
    FILTER_PROPERTY_REPORTER_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_HANDLER_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_RESOLUTION_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_PRODUCT_BUILD => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_PRODUCT_VERSION => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_HIDE_STATUS_ID => Array(
      '0' => META_FILTER_NONE,
    ),
    FILTER_PROPERTY_MONITOR_USER_ID => Array(
      '0' => META_FILTER_ANY,
    ),
    FILTER_PROPERTY_SORT_FIELD_NAME => 'last_updated,vote_count,date_submitted',
    FILTER_PROPERTY_SORT_DIRECTION => 'DESC,DESC,DESC'
  );
  $url_link_parameters = FILTER_PROPERTY_HIDE_STATUS_ID . '=none';

  $rows = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $c_filter );

  html_page_top( 'Dashboard' );

  ?>
  <div class="searchRequests">
    <h3>Search for a Bug or Feature Request</h3>
    <form action="search.php" method="get">
      <input type="text" name="search" value="" />
      <input type="submit" value="Search" />
      <?php echo '<div class="button"><a href="search.php"> Advanced Filters </a></div>'; ?>
    </form>
  </div>
  <div class="requestList">
    <?php foreach( $rows AS $t_bug ){ ?>
    <div class="request">
      <?php
        $t_summary = string_display_line_links( $t_bug->summary );
        $t_last_updated = date( config_get( 'normal_date_format' ), $t_bug->last_updated );
        $status_color = get_status_color( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
        $t_attachment_count = 0;
        if(( file_can_view_bug_attachments( $t_bug->id, null ) ) )
          $t_attachment_count = file_bug_attachment_count( $t_bug->id );
        $project_name = project_get_field( $t_bug->project_id, 'name' );

        // echo '<pre>'. print_r($t_bug,1) .'</pre>';
        echo '<div class="votes">';
          echo '<b>'. $t_bug->vote_count .'</b> <span>vote'. ($t_bug->vote_count != 1 ? 's' : '') .'</span>';
          if( !bug_is_readonly( $t_bug->id ) && !bug_is_resolved( $t_bug->id ) )
            echo wbVoteTrackerPlugin::html_button_vote( $t_bug->id, $t_bug, array('fields'=>array('mode' => 'quick')) );
        echo '</div>';
        echo '<div class="info">';

          echo '<div class="key">'; print_bug_link( $t_bug->id ); echo '</div>';

          echo '<div class="summary">';
            if( ON == config_get( 'show_bug_project_links' ) && helper_get_current_project() != $t_bug->project_id ) {
              echo '[', string_display_line( project_get_name( cproject_id ) ), '] ';
            }
            echo '<a href="view.php?id='.$t_bug->id.'">'.$t_summary.'</a>';
            // if( !bug_is_readonly( $t_bug->id ) && access_has_bug_level( $t_update_bug_threshold, $t_bug->id ) )
            //   echo '<a href="' . string_get_bug_update_url( $t_bug->id ) . '" class="update"><img border="0" src="' . $t_icon_path . 'update.png' . '" alt="' . lang_get( 'update_bug_button' ) . '" /></a>';
          echo '</div>';

          $cat_name = category_full_name( $t_bug->category_id, true, $t_bug->project_id );
          echo '<div class="category '.strtolower(preg_replace('/[^A-Za-z0-9\-\_]+/','',$cat_name)).'">';
          echo string_display_line( $cat_name );
          echo '</div>';

          $status_color = get_status_color( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
          echo '<div class="status" style="background-color:'.$status_color.'">';
          printf( '<span class="issue-status" title="%s">%s</span>',
            get_enum_element( 'resolution', $t_bug->resolution, auth_get_current_user_id(), $t_bug->project_id ),
            get_enum_element( 'status', $t_bug->status, auth_get_current_user_id(), $t_bug->project_id )
          );
          if(( ON == config_get( 'show_assigned_names' ) ) && ( $t_bug->handler_id > 0 ) && ( access_has_project_level( config_get( 'view_handler_threshold' ), $t_bug->project_id ) ) ) {
            printf( ' (%s)', prepare_user_name( $t_bug->handler_id ) );
          }
          echo '</div>';

          echo '<div class="stats">';
            echo (int)$t_bug->bugnotes_count . ' Note' . ((int)$t_bug->bugnotes_count == 1 ? '' : 's');
            echo ' | ';
            echo (int)$t_bug->attachment_count . ' File' . ((int)$t_bug->attachment_count == 1 ? '' : 's');
          echo '</div>';

          echo '<br/>';
          echo '<div class="priority">';
          print_formatted_priority_string( $t_bug );
          echo '</div>';

          echo '<div class="created">Posted by ';
          if( $t_bug->reporter_id > 0 )
            echo prepare_user_name( $t_bug->reporter_id );
          echo '</div>';

          echo '<div class="updated">Updated ';
          if( $t_bug->last_updated > strtotime( '-' . $t_filter[FILTER_PROPERTY_HIGHLIGHT_CHANGED] . ' hours' ) ) {
            echo '<b>' . $t_last_updated . '</b>';
          } else {
            echo $t_last_updated;
          }
          echo '</div>';

        echo '</div>';
      ?>
      <div class="clr"></div>
    </div>
    <?php } ?>
  </div>
  <?php

  html_page_bottom();