<?php

/************************************************************************************************************************************
 *
 * wbVoteTracker plugin for MantisBT
 * 2013 - David Hunt, Webuddha.com
 *
 ************************************************************************************************************************************/

class wbVoteTrackerHelper {

  function category_full_name( $p_category_id, $p_show_project = true, $p_current_project = null ) {
    if( 0 == $p_category_id ) {
      # No Category
      return lang_get( 'no_category' );
    } else if( !category_exists( $p_category_id ) ) {
      return '@' . $p_category_id . '@';
    } else {
      $t_row = category_get_row( $p_category_id );
      $t_project_id = $t_row['project_id'];
      $t_current_project = is_null( $p_current_project ) ? helper_get_current_project() : $p_current_project;
      if( $p_show_project && $t_project_id != $t_current_project ) {
        if( $t_project_id ){
          return '[' . project_get_name( $t_project_id ) . '] ' . $t_row['name'];
        }
        return $t_row['name'];
      }
      return $t_row['name'];
    }
  }

}