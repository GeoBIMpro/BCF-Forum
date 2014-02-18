<?php
/*
Plugin Name: BIM BCF Management
Plugin URI:
Description: Adds BCF 2.0 issue management to WordPress, upload zip archives with issues or add them through a form and keep track of your issues and their details.
Version: 1.0
Author: Bastiaan Grutters
Author URI: http://www.bastiaangrutters.nl
Usage:
Using shortcodes:
[showIssues]
[showIssue]
[showAddZipForm]
[showAddIssueForm]

Or using php functions in templates:
<?php
if( class_exists( 'BIMBCFManagement' ) ) {
	// Show list of issues for this user
	BIMBCFManagement::showIssues();
	// Show details of the supplied issue's id if accessible for the current user
	BIMBCFManagement::showIssue( $issueId );
	// Display a form with which a zip archive of issues can be imported
	BIMBCFManagement::showAddZipForm();
	// Display a form with which an issue can be added
	BIMBCFManagement::showAddIssueForm();
}
?>
*/

include( 'BIMsie.php' );

class BIMBCFManagement {
	private $options;

	public function __construct() {
		$this->options = get_option( 'bim_bcf_management_options', Array() );
		// Check default options and set if missing
		if( !isset( $this->options[ 'bcf_issue_post_type' ] ) ) {
			$this->options[ 'bcf_issue_post_type' ] = 'bcf_issue';
		}
		if( !isset( $this->options[ 'bcf_project_post_type' ] ) ) {
			$this->options[ 'bcf_project_post_type' ] = 'bcf_project';
		}

		// --- Action hooks ---
		// Add options menu page to menu
		add_action( 'admin_menu', Array( 'BIMBCFManagement', 'addOptionsMenu' ) );
		// Add post types etc at the WordPress init action
		add_action( 'init', Array( 'BIMBCFManagement', 'wordPressInit' ) );
		// Add script files
		add_action( 'wp_enqueue_scripts', Array( 'BIMBCFManagement', 'enqueueScripts' ) );

		// --- Add shortcodes ---
		add_shortcode( 'showIssues', Array( 'BIMBCFManagement', 'showIssues' ) );
		add_shortcode( 'showIssue', Array( 'BIMBCFManagement', 'showIssue' ) );
		add_shortcode( 'showAddZipForm', Array( 'BIMBCFManagement', 'showAddZipForm' ) );
		add_shortcode( 'showAddIssueForm', Array( 'BIMBCFManagement', 'showAddIssueForm' ) );
	}

	public static function wordPressInit() {
		$postTypeArguments = Array(
				'labels' => Array(
						'name' => _x( 'BCF Issues', 'post type general name' ),
						'singular_name' => _x( 'BCF Issue', 'post type singular name'),
						'add_new' => __( 'Add New' ),
						'add_new_item' => __( 'Add New BCF Issue' ),
						'edit_item' => __( 'Edit BCF Issue' ),
						'new_item' => __( 'New BCF Issue' ),
						'all_items' => __( 'All BCF Issues' ),
						'view_item' => __( 'View BCF Issue' ),
						'search_items' => __( 'Search BCF Issues' ),
						'not_found' =>  __( 'No BCF issue found' ),
						'not_found_in_trash' => __( 'No BCF Issues found in Trash' ),
						'parent_item_colon' => '',
						'menu_name' => 'BCF Issues' ),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'query_var' => true,
				'rewrite' => true,
				'has_archive' => true,
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => Array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' )
		);
		register_post_type( 'bcf_issue', $postTypeArguments );

		$postTypeArguments = Array(
				'labels' => Array(
						'name' => _x( 'BCF Projects', 'post type general name' ),
						'singular_name' => _x( 'BCF Project', 'post type singular name'),
						'add_new' => __( 'Add New' ),
						'add_new_item' => __( 'Add New BCF Project' ),
						'edit_item' => __( 'Edit BCF Project' ),
						'new_item' => __( 'New BCF Project' ),
						'all_items' => __( 'All BCF Projects' ),
						'view_item' => __( 'View BCF Project' ),
						'search_items' => __( 'Search BCF Projects' ),
						'not_found' =>  __( 'No BCF project found' ),
						'not_found_in_trash' => __( 'No BCF Projects found in Trash' ),
						'parent_item_colon' => '',
						'menu_name' => 'BCF Projects' ),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => false,
				'show_in_menu' => false,
				'query_var' => true,
				'rewrite' => true,
				'map_meta_cap' => false,
				'has_archive' => false,
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => Array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' )
		);
		register_post_type( 'bcf_project', $postTypeArguments );

		// Add image sizes for the issue list and details pages
		add_theme_support( 'post-thumbnails' );
		add_image_size( 'issue-list-thumb', 90, 60 );
		add_image_size( 'issue-detail-thumb', 400, 300 );
	}

	public static function addOptionsMenu() {
		add_options_page( 'BCF Management Options', 'BCF Management',
			'activate_plugins', 'bim_bcf_management_options',
			Array( 'BIMBCFManagement', 'showOptionsPage' ) );
	}

	public static function showOptionsPage() {
		include( plugin_dir_path( __FILE__ ) . 'bim-bcf-management-options.php' );
	}

	public static function showIssues() {
		//print( "showIssues()<br />" );
		$options = BIMBCFManagement::getOptions();
		$myIssues = get_posts( Array(
				'posts_per_page' => -1,
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_status' => 'publish',
				'orderby' => 'date',
				'order' => 'DESC',
				'meta_query' => Array(
						Array(
								'key' => 'import_status',
								'value' => 'complete'
						)
				)
		) );
		if( count( $myIssues ) > 0 ) {
			$index = 0;
?>
			<table class="issue-table">
				<tr>
					<th>&nbsp;</th>
					<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Author', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Project/Revision', 'bim-bcf-management' ); ?></th>
				</tr>
<?php
			foreach( $myIssues as $issue ) {
				$projects = get_post_meta( $issue->ID, 'project' );
				$projectNames = '';
				foreach( $projects as $project ) {
					if( $projectNames != '' ) {
						$projectNames .= ', ';
					}
					$projectNames .= $project[ 'name' ] . ': ' . $project[ 'revision' ];
				}
				$author = get_post_meta( $issue->ID, 'Author', true );
				$timestamp = strtotime( $issue->post_date );
?>
				<tr class="<?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>">
					<td><?php print( get_the_post_thumbnail( $issue->ID, 'issue-list-thumb' ) ); ?></td>
					<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID );  ?>"><?php print( $issue->post_title ); ?></a></<td>
					<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></<td>
					<td><?php print( $author == '' ? '-' : $author ); ?></<td>
					<td><?php print( $projectNames == '' ? '-' : $projectNames ); ?></<td>
				</tr>
<?php
				$index ++;
			}
?>
			</table>
<?php
		} else {
?>
			<p><?php _e( 'No BCF issues imported yet', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function showIssue() {
		//print( "showIssue()<br />" );
		global $post;
		$issueId = ( isset( $_GET[ 'id' ] ) && ctype_digit( $_GET[ 'id' ] ) ) ? $_GET[ 'id' ] : -1;
		if( $issueId == -1 && isset( $post ) && isset( $post->ID ) ) {
			// if no id is supplied we assume the current post is the issue we want to display
			$issueId = $post->ID;
		}
		if( $issueId != -1 ) {
			$options = BIMBCFManagement::getOptions();
			$currentUserId = get_current_user_id();
			$issue = get_post( $issueId );
			if( $issue->post_author == $currentUserId && $issue->post_type == $options[ 'bcf_issue_post_type' ] ) {
				$projects = get_post_meta( $issue->ID, 'project' );
				$projectNames = '';
				$revisions = '';
				foreach( $projects as $project ) {
					if( $projectNames != '' ) {
						$projectNames .= ', ';
					}
					$projectNames .= $project[ 'name' ];
					if( $revisions != '' ) {
						$revisions .= ', ';
					}
					$revisions .= $project[ 'revision' ];
				}
				$author = get_post_meta( $issue->ID, 'Author', true );
				$verbalStatus = get_post_meta( $issue->ID, 'VerbalStatus', true );
				$status = get_post_meta( $issue->ID, 'Status', true );
				$timestamp = strtotime( $issue->post_date );
?>
			<div class="issue-image"><?php print( get_the_post_thumbnail( $issueId, 'issue-detail-thumb' ) ); ?></div>
			<h3><?php print( $issue->post_title ); ?></h3>
			<p class="the-comment"><?php print( get_post_meta( $issue->ID, 'comment', true ) ); ?></p>
			<table class="issue-table">
				<tr>
					<td><?php _e( 'Date', 'bim-bcf-management' ); ?></td>
					<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Author', 'bim-bcf-management' ); ?></td>
					<td><?php print( $author != '' ? $author : '-' );  ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Revision', 'bim-bcf-management' ); ?></td>
					<td><?php print( $revisions != '' ? $revisions : '-' );  ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Project', 'bim-bcf-management' ); ?></td>
					<td><?php print( $projectNames != '' ? $projectNames : '-' );  ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Verbal status', 'bim-bcf-management' ); ?></td>
					<td><?php print( $verbalStatus != '' ? $verbalStatus : '-' );  ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Status', 'bim-bcf-management' ); ?></td>
					<td><?php print( $status != '' ? $status : '-' );  ?></td>
				</tr>

			</table>
<?php
			} else {
?>
			<p><?php _e( 'Issue not accessible', 'bim-bcf-management' ); ?></p>
<?php
			}
		} else {
?>
			<p><?php _e( 'No issue selected', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function showAddZipForm() {
		if( isset( $_FILES[ 'bcf_zip_file' ] ) ) {
			if( isset( $_FILES[ 'bcf_zip_file' ][ 'error' ] ) && $_FILES[ 'bcf_zip_file' ][ 'error' ] != 0 ) {
				$errorMessage = __( 'Could not upload the file, contact a system administrator.', 'bim-bcf-management' ) . ' Error code: ' . $_FILES[ 'bcf_zip_file' ][ 'error' ];
			}
			if( !isset( $errorMessage ) ) {
				$errorMessage = '';
				$zip = zip_open( $_FILES[ 'bcf_zip_file' ][ 'tmp_name' ] );
				if( is_resource( $zip ) ) {
					$files = Array();
					$guids = Array();
					while( ( $entry = zip_read( $zip ) ) !== false ) {
						// Every line should be a file from an issue
						$xml = '';
						$entryName = explode( '/', zip_entry_name( $entry ) );
						if( count( $entryName ) > 1 ) {
							$guid = $entryName[count( $entryName ) - 2];
							$filename = $entryName[count( $entryName ) - 1];
							if( zip_entry_open( $zip, $entry ) ) {
								while( ( $subEntry = zip_entry_read( $entry ) ) !== false && $subEntry != '' ) {
									$xml .= $subEntry;
								}
								zip_entry_close( $entry );
							}
						}
						if( !in_array( $guid, $guids ) ) {
							if( count( $files ) > 1 ) {
								// Import this XML
								if( !BIMBCFManagement::addIssueFromZip( $files ) ) {
									$filesError = __( 'Error at import', 'bim-bcf-management' ) . ' (guid: ' . $guid . ', files: ';
									$firstFile = true;
									foreach( $files as $file ) {
										if( !$firstFile ) {
											$filesError .= ', ';
										} else {
											$firstFile = false;
										}
										$filesError .= $file[1];
									}
									$errorMessage .= $filesError;
								}
								$files = Array();
							}
							$guids[] = $guid;
						}
						$files[] = Array( $guid, $filename, $xml );
					}
					if( count( $files ) > 0 ) {
						$errorMessage .= BIMBCFManagement::addIssueFromZip( $files );
					}
					zip_close( $zip );
				} else {
					$errorMessage = __( 'Could not open the zip archive.', 'bim-bcf-management' ) . ' Error code: ' . $zip;
				}
			}

			if( isset( $errorMessage ) && $errorMessage != '' ) {
?>
				<p class="form-error-message"><?php print( $errorMessage ); ?></p>
<?php
			}
		}

		$options = BIMBCFManagement::getOptions();
		$unsetIssues = get_posts( Array(
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'posts_per_page' => -1,
				'author' => get_current_user_id(),
				'meta_query' => Array(
						Array(
								'key' => 'import_status',
								'value' => 'pending'
						)
				)
		) );

		if( count( $unsetIssues ) > 0 ) {
			$projectIds = Array();
			foreach( $unsetIssues as $unsetIssue ) {
				$markups = get_post_meta( $unsetIssue->ID, 'markup' );
				foreach( $markups as $markup ) {
					if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
						foreach( $markup[ 'Header' ][ 'File' ] as $file ) {
							if( isset( $file[ '@attributes' ] ) && isset( $file[ '@attributes' ][ 'IfcProject' ] ) && $file[ '@attributes' ][ 'IfcProject' ] != '' ) {
								if( !in_array( $file[ 'Filename' ] . ', ifcProject: ' . $file[ '@attributes' ][ 'IfcProject' ], $projectIds ) ) {
									$projectIds[] = $file[ 'Filename' ] . ', ifcProject: ' . $file[ '@attributes' ][ 'IfcProject' ];
								}
							}
						}
					}
				}
			}
?>
				<h3><?php _e( 'Some issues are not linked to revisions and/or projects', 'bim-bcf-management' ); ?></h3>
				<table class="issue-table" id="update-issue-revision-table">
					<tr>
						<th>&nbsp;</th>
						<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Files', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Author', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Projects', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Revision', 'bim-bcf-management' ); ?></th>
					</tr>
<?php
			$index = 0;
			foreach( $unsetIssues as $unsetIssue ) {
				$author = get_post_meta( $unsetIssue->ID, 'Author', true );
				$timestamp = strtotime( $unsetIssue->post_date );
				$markups = get_post_meta( $unsetIssue->ID, 'markup' );
				$files = 0;
				foreach( $markups as $markup ) {
					if( isset( $markup[ 'Header' ][ 'File' ] ) && is_array( $markup[ 'Header' ][ 'File' ] ) ) {
						$files += count( $markup[ 'Header' ][ 'File' ] );
					}
				}
?>
					<tr class="issue-pending <?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>" id="issue-<?php print( $unsetIssue->ID ); ?>">
						<td><?php print( get_the_post_thumbnail( $unsetIssue->ID, 'issue-list-thumb' ) ); ?></td>
						<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $unsetIssue->ID );  ?>" target="_blank"><?php print( $unsetIssue->post_title ); ?></a></td>
						<td class="numeric"><?php print( $files ); ?></td>
						<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></td>
						<td><?php print( $author == '' ? '-' : $author ); ?></td>
						<td class="project"></td>
						<td class="revision"></td>
					</tr>
<?php
				$index ++;
			}
			$bimsieServers = BIMBCFManagement::getBimsieServers();
?>
				</table>
				<script type="text/javascript">
					var bimBCFManagementSettings = {
						ajaxURI: "<?php print( plugins_url( 'ajax-handler.php' , __FILE__ ) ); ?>",
						loadingImage: "<img class=\"loading-image\" src=\"<?php bloginfo( 'wpurl' ); ?>/wp-admin/images/loading.gif\" alt=\"loading...\" />",
						bimsieServers: <?php print( json_encode( $bimsieServers ) ); ?>,
						ifcProjects: <?php print( json_encode( $projectIds ) ); ?>,
						text: {
							selectServerTitle: "<?php _e( 'Select a BIMsie server or enter a new one', 'bim-bcf-management' ); ?>",
							selectServerTitle: "<?php _e( 'Select the project for each file', 'bim-bcf-management' ); ?>",
							newServerLabel: "<?php _e( 'Add BIMSie server URI', 'bim-bcf-management' ); ?>",
							submitServer: "<?php _e( 'Retrieve information', 'bim-bcf-management' ); ?>",
							selectServerLabel: "<?php _e( 'Select BIMSie server', 'bim-bcf-management' ); ?>",
							noServerOption: "--- <?php _e( 'New server', 'bim-bcf-management' ); ?> ---",
							rememberServerLabel: "<?php _e( 'Remember user', 'bim-bcf-management' ); ?>",
							serverUserLabel: "<?php _e( 'Username', 'bim-bcf-management' ); ?>",
							serverPasswordLabel: "<?php _e( 'Password', 'bim-bcf-management' ); ?>",
							serverSubmitError: "<?php _e( 'Supply a BIMSie server URI, username and password or select one from your list.', 'bim-bcf-management' ); ?>",
							noProjectsFoundMessage: "<?php _e( 'No projects could be found on this BIMSie server for this user.', 'bim-bcf-management' ); ?>",
							revision: "<?php _e( 'Revision', 'bim-bcf-management' ); ?>"
						}
					};
				</script>
<?php
		} else {
?>
			<form method="post" action="" enctype="multipart/form-data">
				<label for="bcf-zip-file"><?php _e( 'Select a BCF zip archive', 'bim-bcf-management' ); ?></label><br />
				<input type="file" id="bcf-zip-file" name="bcf_zip_file" /><br />
				<br />
				<input type="submit" value="<?php _e( 'Add', 'bim-bcf-management' ); ?>" />
			</form>
<?php
		}
	}

	private static function addIssueFromZip( $files ) {
		$guid = '';
		$markup = Array();
		$project = false;
		$viewpoints = Array();
		$snapshots = Array();

		foreach( $files as $file ) {
			if( $guid == '' ) {
				// Remember the guid, is the same for all files
				$guid = $file[0];
			}

			if( $file[1] == 'markup.bcf' ) {
				// extract the XML from the markup
				$xml = simplexml_load_string( $file[2] );
				$markup = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( $file[1] == 'viewpoint.bcfv' || substr( $file[1], strlen( $file[1] ) - 5 ) == '.bcfv' ) {
				//print( "viewpoint found: {$file[1]}<br />" );
				// extract the XML from viewpoint
				$xml = simplexml_load_string( $file[2] );
				$viewpoints[] = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( $file[1] == 'project.bcfp' ) {
				//print( "project file found: {$file[1]}<br />" );
				// extract the XML from the project file
				$xml = simplexml_load_string( $file[2] );
				$project = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} else {
				//print( "snapshot found: {$file[1]}<br />" );
				// This should be a screenshot file
				// store this to be added after we created the issue post
				$snapshots[] = Array( $file[1], $file[2] );
			}
		}
		//var_dump( $guid, $markup, $project, $viewpoints );
		// Create a post with information from the XML issue
		$options = BIMBCFManagement::getOptions();
		$currentUserId = get_current_user_id();

		$postData = Array(
				'post_title' => wp_strip_all_tags( $guid ),
				'post_content' => '',
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_status' => 'publish',
				'post_author' => $currentUserId
			);
		if( isset( $markup[ 'Comment' ] ) && isset( $markup[ 'Comment' ][ 'Date' ] ) ) {
			$date = strtotime( $markup[ 'Comment' ][ 'Date' ] );
			$postData[ 'post_date' ] = date( 'Y-m-d H:i:s', $date );
		}
		$postId = wp_insert_post( $postData );
		if( $postId > 0 ) {
			// Set post meta so we know this issue has yet to be attached to a project/revision
			add_post_meta( $postId, 'import_status', 'pending', true );

			
			// Replace backslashes with slashes to prevent escape issues
			$markup = str_replace( '\\', '/', $markup );
			// Store XML stuff in post meta
			add_post_meta( $postId, 'markup', $markup, true );

			// Set some information for easier access/filtering
			if( isset( $markup[ 'Comment' ] ) ) {
				if( isset( $markup[ 'Comment' ][ 'VerbalStatus' ] ) ) {
					add_post_meta( $postId, 'VerbalStatus', $markup[ 'Comment' ][ 'VerbalStatus' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Status' ] ) ) {
					add_post_meta( $postId, 'Status', $markup[ 'Comment' ][ 'Status' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Author' ] ) ) {
					add_post_meta( $postId, 'Author', $markup[ 'Comment' ][ 'Author' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Comment' ] ) ) {
					add_post_meta( $postId, 'Comment', $markup[ 'Comment' ][ 'Comment' ], true );
				}
			}
			foreach( $viewpoints as $viewpoint ) {
				add_post_meta( $postId, 'viewpoint', $viewpoint, false );
			}

			// TODO: Could set some more values to filter on for this issue

			// If a project is set, add it as post meta
			if( $project !== false ) {
				// TODO: this should be set, but could check it to be sure
				add_post_meta( $postId, 'ProjectId', $project[ '@attributes' ][ 'ProjectId' ], true );
				if( isset( $project[ 'Name' ] ) ) {
					add_post_meta( $postId, 'ProjectName', $project[ 'Name' ], true );
				}
			}

			// Add snapshots as attachments (first one is the thumbnail!)
			$first = true;
			foreach( $snapshots as $snapshot ) {
				BIMBCFManagement::writeSnapshot( $postId, $snapshot, $first );
				if( $first ) {
					$first = false;
				}
			}
			return true;
		} else {
			return false;
		}
	}

	// TODO: Should make a single function for adding issues used by other parts of the import
	private static function addIssue( $postData, $metaData ) {

	}

	private static function writeSnapshot( $postId, $snapshot, $first = false ) {
		// We make sure the filename has a dot and extension
		if( strpos( $snapshot[0], '.' ) !== false ) {
			$uploadInfo = wp_upload_dir();
			$index = 1;
			$basename = substr( $snapshot[0], 0, strrpos( $snapshot[0], '.' ) );
			$extension = substr( $snapshot[0], strrpos( $snapshot[0], '.' ) + 1 );
			$filename = $snapshot[0];
			// Find the first available filename
			// TODO: can this get slow? If that is the case we could store the index for the current year/month and read it from there
			while ( file_exists( $uploadInfo[ 'path' ] . '/' . $filename ) ) {
				$index ++;
				$filename = $basename . '-' . $index . '.' . $extension;
			}
			$file = fopen( $uploadInfo[ 'path' ] . '/' . $filename, 'w' );
			if( $file !== false ) {
				fwrite( $file, $snapshot[1] );
				fclose( $file );
				// Add the snapshot as an attachment to the project
				$wpFiletype = wp_check_filetype( basename( $filename ), null );
				$attachment = Array(
						'guid' => $uploadInfo[ 'url' ] . '/' . basename( $filename ),
						'post_mime_type' => $wpFiletype[ 'type' ],
						'post_title' => preg_replace('/\.[^.]+$/', '', basename( $filename ) ),
						'post_content' => '',
						'post_status' => 'inherit'
				);
				$attachId = wp_insert_attachment( $attachment, $uploadInfo[ 'path' ] . '/' . $filename, $postId );
				// you must first include the image.php file
				// for the function wp_generate_attachment_metadata() to work
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attachData = wp_generate_attachment_metadata( $attachId, $uploadInfo[ 'path' ] . '/' . $filename );
				wp_update_attachment_metadata( $attachId, $attachData );
				if( $first ) {
					set_post_thumbnail( $postId, $attachId );
				}
			} else {
				return false;
			}
		}
	}

	public static function convertSimpleXML2Array( $simpleXMLObject ) {
		// We expect there to not be any elements with the name attributes
		$xmlArray = Array( '@attributes' => Array() );
		foreach( $simpleXMLObject->attributes() as $attributeName => $attributeValue ) {
			$xmlArray[ '@attributes' ][$attributeName] = '' . $attributeValue;
		}
		$simpleXMLObject = ( Array ) $simpleXMLObject;
		foreach( $simpleXMLObject as $key => $value ) {
			if( $key != '@attributes' ) {
				if( is_string( $value ) ) {
					$xmlArray[$key] = $value;
				} elseif( is_array( $value ) ) {
					$values = Array();
					foreach( $value as $subKey => $subValue ) {
						$values[] = BIMBCFManagement::convertSimpleXML2Array( $subValue );
					}
					$xmlArray[$key] = $values;
				} elseif( get_class( $value ) == 'SimpleXMLElement' ) {
					$xmlArray[$key] = BIMBCFManagement::convertSimpleXML2Array( $value );
				} else {
					// Could happen if a boolean or integer is found, but not sure if SimpleXML casts those to none string type
					print( "Unexpected value in XML object: $key => $value<br />\n" );
					$xmlArray[$key] = $value;
				}
			}
		}
		return $xmlArray;
	}

	public static function showAddIssueForm() {
		print( "showAddIssueForm()<br />" );
	}

	public static function getOptions( $forceReload = false ) {
		global $bimBCFManagement;
		if( $forceReload ) {
			$bimBCFManagement->options = get_option( 'bim_bcf_management_options', Array() );
		}
		return $bimBCFManagement->options;
	}

	public static function enqueueScripts() {
		wp_enqueue_style( 'bim-bcf-management', plugins_url( '/bim-bcf-management.css' , __FILE__ ) );
		// TODO: maybe I should only add this on certain pages and not everywhere? Think about it!
		wp_enqueue_script( 'bim-bcf-management', plugins_url( '/bim-bcf-management.js' , __FILE__ ), Array( 'jquery' ), '1.0.0', true );
	}

	public static function getBimsieServers( $excludeAuthInfo = true ) {
		$bimsieServers = get_user_meta( get_current_user_id(), 'bimsie-servers' );
		if( $excludeAuthInfo ) {
			$servers = Array();
			foreach( $bimsieServers as $bimsieServer ) {
				$server = Array( 'uri' => $bimsieServer[ 'uri' ], 'remember' => $bimsieServer[ 'remember' ] );
				if( $bimsieServer[ 'remember' ] == 1 && isset( $bimsieServer[ 'username' ] ) ) {
					$server[ 'username' ] = $bimsieServer[ 'username' ];
				}
				$servers[] = $server;
			}
			return $servers;
		} else {
			return $bimsieServers;
		}
	}
	
	public static function getBimsieServerById( $serverId ) {
		$servers = BIMBCFManagement::getBimsieServers( false );
		if( isset( $servers[$serverId] ) ) {
			return $servers[$serverId];
		} else {
			return false;
		}
	}

	public static function setProjectForPendingIssues( $projects, $projectNames = Array(), $revisions = Array() ) {
		$options = BIMBCFManagement::getOptions();
		$allDone = false;
		$unsetIssues = get_posts( Array(
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'posts_per_page' => -1,
				'author' => get_current_user_id(),
				'meta_query' => Array(
						Array(
								'key' => 'import_status',
								'value' => 'pending'
						)
				)
		) );
		
		$projectsMissingRevisions = Array();

		if( count( $unsetIssues ) > 0 ) {
			$projectIds = Array();
			$projectIdsCheck = Array();
			foreach( $unsetIssues as $unsetIssue ) {
				$markups = get_post_meta( $unsetIssue->ID, 'markup' );
				foreach( $markups as $markup ) {
					if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
						foreach( $markup[ 'Header' ][ 'File' ] as $file ) {
							if( isset( $file[ '@attributes' ] ) && isset( $file[ '@attributes' ][ 'IfcProject' ] ) && $file[ '@attributes' ][ 'IfcProject' ] != '' ) {
								if( !in_array( $file[ '@attributes' ][ 'IfcProject' ] . $file[ 'Filename' ], $projectIdsCheck ) ) {
									$projectIdsCheck[] = $file[ '@attributes' ][ 'IfcProject' ] . $file[ 'Filename' ];
									$projectIds[] = $file[ '@attributes' ][ 'IfcProject' ];
								}
							}
						}
					}
				}
			}
			// We fill this array with the projects where we have no revision yet
			if( count( $projectIds ) == count( $projects ) ) { // This should match... how can it not?
				$allDone = true;
				foreach( $unsetIssues as $unsetIssue ) {
					$markups = get_post_meta( $unsetIssue->ID, 'markup' );
					delete_post_meta( $unsetIssue->ID, 'project' );
					$issueDone = true;
					foreach( $markups as $markup ) {
						if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
							foreach( $markup[ 'Header' ][ 'File' ] as $file ) {
								if( isset( $file[ '@attributes' ] ) && isset( $file[ '@attributes' ][ 'IfcProject' ] ) && $file[ '@attributes' ][ 'IfcProject' ] != '' ) {
									foreach( $projectIds as $key => $value ) {
										if( $projectIdsCheck[$key] == $file[ '@attributes' ][ 'IfcProject' ] . $file[ 'Filename' ] ) {
											// We found the right project guid
											// store the project oid for this issue
											add_post_meta( $unsetIssue->ID, 'project', Array( 'ifcProject' => $value, 'file' => $file[ 'Filename' ], 'name' => ( isset( $projectNames[$key] ) ? $projectNames[$key] : '' ), 'oid' => $projects[$key], 'revision' => ( ( isset( $revisions[$key] ) && $revisions[$key] != '' ) ? $revisions[$key] : -1 ) ) );
											if( !isset( $projectsMissingRevisions[$key] ) ) {
												$projectsMissingRevisions[$key] = Array( 'ifcProject' => $value, 'file' => $file[ 'Filename' ], 'oid' => $projects[$key], 'name' => ( isset( $projectNames[$key] ) ? $projectNames[$key] : '' ), 'revision' => ( ( isset( $revisions[$key] ) && $revisions[$key] != '' ) ? $revisions[$key] : -1 ) );
											}
											if( !isset( $revisions[$key] ) || $revisions[$key] == '' || $revisions[$key] == -1 ) {
												$issueDone = false;
												$allDone = false;
											}
											break 1;
										}
									}
								}
							}
						}
					}
					if( $issueDone ) {
						update_post_meta( $unsetIssue->ID, 'import_status', 'complete' );
					}
				}
			}
		}
		if( $allDone ) {
			$projectsMissingRevisions = Array();
		}
		return $projectsMissingRevisions;
	}
}

$bimBCFManagement = new BIMBCFManagement();
