<?php
/**
 * Plugin Name: Utf8ize
 * Plugin URI: http://wordpress.org/extend/plugins/utf8ize/
 * Description: Convert all your database character sets to utf8, trying to follow Codex guides. The plugin return SQL statements and you have to run it manually to apply the conversion.
 * Author: PressLabs
 * Version: 1.1
 * Author URI: http://www.presslabs.com/
 */

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'utf8ize_settings_link' );
function utf8ize_settings_link( $links ) {
	$settings_link = '<a href="tools.php?page=' . plugin_basename( __FILE__ ) . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

function utf8ize_generator() {
	mysql_connect( DB_HOST, DB_USER, DB_PASSWORD ) or die( 'Could not connect: ' . mysql_error() );
	mysql_set_charset( DB_CHARSET ) or die( 'Could not set charset: ' . mysql_error() );
	mysql_select_db( DB_NAME ) or die( 'Could not select database: ' . mysql_error() );

	$db_collate = ( ! defined( 'DB_COLLATE' ) || '' == DB_COLLATE ? 'utf8_unicode_ci' : DB_COLLATE );

	function mysql_get_results( $query, $die_on_error = true ) {
		$result = mysql_query( $query );
		if ( false === $result ) {
			echo "Error on query: $query\n";
			echo "\t" . mysql_error();
			echo "\n";
			if ( $die_on_error ) {
				exit(1);
			}
		}
		if ( true === $result ) {
			return true;
		}
		$_ret = array();
		while ( $row = mysql_fetch_assoc( $result ) ) {
			$_ret[] = $row;
		}

		return $_ret;
	}

	$statements = array( 'ALTER DATABASE ' . DB_NAME . ' CHARACTER SET utf8 COLLATE ' . $db_collate );
	$statements[] = 'USE `' . DB_NAME . '`';

	$tables = array();
	$results = mysql_get_results( 'SHOW TABLE STATUS' );
	foreach ( $results as $row ) {
		$tables[] = $row['Name'];
		if ( ! preg_match( '/utf8_/', $row['Collation'] ) ) {
			$statements[] = 'ALTER TABLE `' . $row['Name'] . '` DEFAULT CHARACTER SET utf8 COLLATE ' . $db_collate;
		}
	}

	$_types = array(
		'VARCHAR'  => 'VARBINARY',
		'LONGTEXT' => 'LONGBLOB',
		'TINYTEXT' => 'TINYBLOB',
		'CHAR'     => 'BINARY',
		'TEXT'     => 'BLOB',
	);

	foreach ( $tables as $table ) {
		$columns  = mysql_get_results( 'SHOW FULL COLUMNS FROM `' . $table . '`' );
		$indexes  = mysql_get_results( 'SHOW INDEX FROM `' . $table . '`' );
		$fulltext = array();
		foreach ( $indexes as $index ) {
			if ( $index['Index_type'] != 'FULLTEXT' ) {
				continue;
			}
			if ( ! isset( $fulltext[ $index['Key_name'] ] ) ) {
				$fulltext[ $index['Key_name'] ] = array();
				$fulltext[ $index['Key_name'] ][ $index['Seq_in_index'] ] = $index['Column_name'];
			}
		}
		$_fulltext = array();

		foreach ( $columns as $column ) {
			if ( ! preg_match( '/utf8_/', $column['Collation'] ) ) {
				foreach ( $fulltext as $index_name => $index ) {
					if ( in_array( $column['Field'], $index ) ) {
						$statements[] = "ALTER TABLE `$table` DROP INDEX `$index_name`";
						$_fulltext[]  = "ALTER TABLE `$table` ADD FULLTEXT `$index_name` (" . join(', ', array_map( create_function( '$s' , 'return "`$s`";' ), $index ) ) .")";
						unset( $fulltext[ $index_name ] );
					}
				}
			}
		}

		foreach ( $columns as $column ) {
			if ( $column['Collation'] == '' ) {
				continue;
			}
			if ( ! preg_match( '/utf8_/', $column['Collation'] ) ) {
				$c    = '';
				$type = strtoupper( $column['Type'] );
				if ( preg_match( '/^(ENUM|SET)/', $type ) ) {
					$null         = ( $column['Null'] == 'NO' ? 'NOT NULL' : 'NULL' );
					$default      = ( $column['Default'] ? 'DEFAULT \'' . mysql_real_escape_string( $column['Default'] ) . '\'' : '' );
					$statements[] = trim( "ALTER TABLE `$table` CHANGE $column[Field] $column[Field] $type CHARACTER SET utf8 $null $default" );
				} else {
					$btype = str_replace( array_keys( $_types ), $_types, $type );
					if ( $type != $btype ) {
						$statements[] = "ALTER TABLE `$table` CHANGE `$column[Field]` `$column[Field]` $btype";
						$statements[] = "ALTER TABLE `$table` CHANGE `$column[Field]` `$column[Field]` $type CHARACTER SET utf8 COLLATE $db_collate";
					} else {
						fprintf( STDERR, "WARNING: No binary equivalent for $type. Data scrambling is likely to occur.\n" );
						$statements[] = "ALTER TABLE `$table` CHANGE `$column[Field]` `$column[Field]` $type CHARACTER SET utf8 COLLATE $db_collate";
					}
				}
			}
		}

		foreach ( $_fulltext as $index ) {
			$statements[] = $index;
		}
	}
	echo join( ";\n", $statements );
	echo ";\n";
}

function utf8ize_options() {
	if ( isset( $_POST['submit_settings'] ) ) {
		utf8ize_update_options();
	}

	isset( $_GET['tab'] ) ? $selected_tab = $_GET['tab'] : $selected_tab = 'generator';
?>
<div class="wrap">

<div id="icon-tools" class="icon32">&nbsp;</div>
<h2 class="nav-tab-wrapper">
<a class="nav-tab<?php if ( 'generator' == $selected_tab ) { echo ' nav-tab-active'; } ?>" href="tools.php?page=utf8ize/utf8ize.php&tab=generator">SQL generator</a>
<a class="nav-tab<?php if ( 'documentation' == $selected_tab ) { echo ' nav-tab-active'; } ?>" href="tools.php?page=utf8ize/utf8ize.php&tab=documentation">Documentation</a>
</h2>

<?php if ( 'generator' === $selected_tab ) { ?>
	<p>If you run the following SQL statements, you will convert all your database character sets to utf8, trying to follow <strong><a href="http://codex.wordpress.org/Converting_Database_Character_Sets">Codex guides</a></strong>. <br /><br />You should use this if you are experiencing double utf8 encoding. You can check this by setting <strong>DB_CHARSET</strong> in your <strong>wp-config.php</strong> file to <strong>latin1</strong> or commenting the line; if your characters look good now on your site than you are probably suffering from this issue.
	It works by scanning all you tables and columns and generating a list of SQL statements which allow you to convert to convert your content to uft8.</p>
	<h3><span style="color:red;"><strong>!!! CAUTION !!!</strong><br />The execution time of the next SQL statements may take a lot of time(even days), related to dimensions of your database and the amount of the content.</span></h3>
	<textarea cols="100" rows="20"><?php utf8ize_generator(); ?></textarea>
<?php } ?>

<?php if ( 'documentation' === $selected_tab ) { ?>
	<h1>Converting Database Character Sets</h1>

	<h2>The History</h2>
	<p>Up to and including WordPress Version 2.1.3, most WordPress databases were created using the latin1 character set and the latin1_swedish_ci collation.</p>
	<p>Beginning with Version 2.2, both the database character set and the collation can be defined in the wp-config.php file. Setting the DB_CHARSET and DB_COLLATE values in wp-config.php causes WordPress to create the database with the appropriate charset settings. The default is UTF8, the standard charset for modern data which supports all internet-friendly languages.</p>
	<p>Note that in addition to setting the format of any new tables created by WordPress the DB_CHARSET property defines the format of content sent to your database and the expected format of content retrieved from it. It does not alter the format of existing tables, so if you have tables formatted with a different character set from the one in DB_CHARSET the results will be eratic both in terms of fetching and saving text.</p>
	<p>The rest of this article will explain how to convert the character set and collation for existing WordPress installations.</p>

	<h2>The basics of converting a database</h2>
	<p>Before beginning any conversion, please back up your database. The Backing Up Your Database article has easy-to-follow instructions.</p>
	<p>Note: If you don't know anything about SQL and MySQL you are probably screwed. This is voodoo-code territory, so you may want to RTFM about MySQL and Charsets before continuing.</p>
	<p>The goal in these conversions is always to decide on what charset/collation combination you want to use (UTF8 being the best choice in almost all scenarios) then to convert all tables/columns in your database to use that charset. At that point you can set DB_COLLATE and DB_CHARSET to the desired charset and collation to match.</p>
	<p>Note: In most cases if a collation is not defined MySQL will assume the default collation for the CHARSET which is specified. For UTF8 the default is utf8_general_ci, which is usually the right choice.</p>
	<p>In the examples below it is assumed you have a database in the latin1 character set that needs converting to a utf8 character set. latin1 is the tragic default of MySQL and the most likely to be the problematic format of older copies of WordPress. UFT8 is the best way to support all internet-friendly languages.</p>

	<strong>The rest of the story is <a href="http://codex.wordpress.org/Converting_Database_Character_Sets">here</a>.<strong>
<?php } ?>

</div><!-- .wrap -->
<?php
}

add_action( 'admin_menu', 'utf8ize_menu' );
function utf8ize_menu() {
	add_management_page( 'Utf8ize - Options', 'Utf8ize', 'administrator', __FILE__, 'utf8ize_options' );
}
