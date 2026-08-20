function safe_report_comments_flag_comment( comment_id, nonce, result_id ) {
	jQuery.post(
		SafeCommentsAjax.ajaxurl,
		{
			comment_id : comment_id,
			sc_nonce : nonce,
			result_id : result_id,
			action : 'safe_report_comments_flag_comment',
			xhrFields: {
				withCredentials: true
			}
		},
		function( data ) { jQuery( '#' + result_id ).html( data ); }
	);
	return false;
}

jQuery( function( $ ) {
	// Legacy visibility toggling for manually placed links, e.g. a theme that
	// calls do_action( 'comment_report_abuse_link' ) in its comment template.
	$( '.hide-if-js' ).hide();
	$( '.hide-if-no-js' ).show();

	// In automatic mode the report link is rendered at the end of each comment and
	// hidden. Move it next to that comment's reply link, then reveal it. The reply
	// link is found via its core data-commentid attribute, so placement does not
	// depend on the theme's markup (the previous server-side regex broke whenever a
	// theme altered the reply link, e.g. Twenty Twenty prepends a class). This works
	// for classic and block themes alike. Comments at the maximum threading depth have
	// no reply link, so their link is left in place at the end of the comment.
	var positioned = {};

	$( '.safe-comments-report-link' ).each( function() {
		var $wrapper   = $( this );
		var commentId  = $wrapper.data( 'comment-id' );
		var $replyLink = $( '.comment-reply-link[data-commentid="' + commentId + '"]' ).first();

		// Only move the first link for each comment, so a comment shown more than once
		// on a page (for example, also in a widget) does not stack links by the reply link.
		if ( $replyLink.length && ! positioned[ commentId ] ) {
			$replyLink.after( $wrapper );
			positioned[ commentId ] = true;
		}

		$wrapper.show();
	} );
} );
